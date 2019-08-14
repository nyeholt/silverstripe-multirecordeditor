<?php

namespace Symbiote\MultiRecord;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Forms\CompositeField;


/**
 * @author marcus
 */
class MultiRecordEditingField extends CompositeField
{
    /**
     *
     * @var ArrayList
     */
    protected $records;

    /**
     * Whether to override html editor heights
     *
     * @var int
     */
    protected $htmlEditorHeight = 6;

    /**
     * Should we use toggle Composites in layout ?
     *
     * @var boolean
     */
    protected $useToggles = true;

    /**
     * Should we be configured for frontend or CMS based editing?
     *
     * @var boolean
     */
    protected $forFrontendUse = false;

    /**
     * @var FieldList
     */
    protected $children;
    protected $tabs;

    public function __construct($name, $title = null, $recordList = null, $forFrontendUse = false)
    {
        parent::__construct();

        $this->setName($name);
        $this->setTitle($title);

        $this->forFrontendUse = $forFrontendUse;

        $this->children = FieldList::create();

        $this->tabs = FieldList::create();

        $this->records = ArrayList::create();
        if ($recordList) {
            foreach ($recordList as $record) {
                $this->addRecord($record);
            }
        }
    }

    public function isComposite()
    {
        return true; // parent::isComposite();
    }

    public function replaceField($fieldName, $newField, $dataFieldOnly = true)
    {
		// noop for a mr editing field... for now
    }

    public function hasData()
    {
        return true;
    }


    public function removeByName($fieldName, $dataFieldOnly = false)
    {
        return $this->children->removeByName($fieldName, $dataFieldOnly);
    }

    /**
     * Set a height for html editor fields
     *
     * @param int $value
     * @return MultiRecordEditingField
     */
    public function setHtmlEditorHeight($value)
    {
        $this->htmlEditorHeight = $value;
        return $this;
    }

    public function setUseToggles($value)
    {
        $this->useToggles = $value;
        return $this;
    }

    /**
     * Retrieves the list of records that have been edited and return to the user
     *
     * @return ArrayList
     */
    public function getRecords()
    {
        return $this->records;
    }

    public function setForm($form)
    {
        parent::setForm($form);

        foreach ($this->children as $child) {
            $child->setForm($form);
        }
    }

    public function editList($list)
    {
        if ($list) {
            foreach ($list as $record) {
                if ($record instanceof DataObjectInterface) {
                    $this->addRecord($record);
                }
            }
        }
    }

    public function addRecord(DataObjectInterface $record, $parentFields = null)
    {
        $fields = null;
        if (!$record->canEdit()) {
            return;
        }
        $this->records->push($record);
        if (method_exists($record, 'multiEditor')) {
            $editor = $record->multiEditor();
            // add its records to 'me'
            $this->addMultiEditor($editor, $record, true);
            return;
        } else if (method_exists($record, 'multiEditFields')) {
            $fields = $record->multiEditFields();
        } else if ($this->forFrontendUse && method_exists($record, 'getFrontEndFields')) {
            $fields = $record->getFrontEndFields();
        } else {
            $fields = $record->getCMSFields();
        }
        /* @var $fields FieldList */

        $record->extend('updateMultiEditFields', $fields, $this->forFrontendUse);
        // we just want the data fields, not wrappers
        $fields = $fields->dataFields();
        if (!count($fields)) {
            return;
        }

        $status = $record->CMSPublishedState;
        if ($status) {
            $status = ' (' . $status . ')';
        }

        $tab = ToggleCompositeField::create('CompositeHeader' . $record->ID, $record->Title . $status, null);
        if ($parentFields) {
            $parentFields->push($tab);
        } else {
            $tab->setStartClosed(false);
            $this->tabs->push($tab);
        }

        // if we're not using toggles, we only add the header _if_ we're an inner item, ie $parentFields != null
        if ($parentFields) {
            $this->children->push(HeaderField::create('RecordHeader' . $record->ID, $record->Title . $status));
        }

        foreach ($fields as $field) {
            $original = $field->getName();

            // if it looks like a multieditor field, let's skip for now.
            if (strpos($original, '__') > 0) {
                continue;
            }

            if ($field instanceof MultiRecordEditingField) {
                $this->addMultiEditor($field, $record, false, $tab);
                continue;
            }

            $exists = (isset($record->$original) ||
                $record->hasMethod($original) || ($record->hasMethod('hasField') && $record->hasField($original)));

            $val = null;
            if ($exists) {
                $val = $record->__get($original);
            }

            $field->setValue($val, $record);

            // tweak HTMLEditorFields so they're not huge
            if ($this->htmlEditorHeight && $field instanceof HTMLEditorField) {
                $field->setRows($this->htmlEditorHeight);
            }

            // re-write the name to the multirecordediting name for later retrieval.
            // this cannot be done earlier as otherwise, fields that load data from the
            // record won't be able to find the information they're expecting
            $name = $this->getFieldName($field, $record);
            $field->setName($name);

            if (method_exists($field, 'setRecord')) {
                $field->setRecord($record);
            }

            if ($tab) {
                $tab->push($field);
            }

            $this->children->push($field);
        }
    }

    protected function addMultiEditor($editor, $fromRecord, $addHeader = false, $tab = null)
    {
        if ($addHeader) {
            $this->children->push(HeaderField::create('RecordHeader' . $fromRecord->ID, $fromRecord->Title));
        }

        foreach ($editor->getRecords() as $r) {
            $this->addRecord($r, $tab);
        }
    }

    protected function getFieldName($field, $record)
    {
        $name = $field instanceof FormField ? $field->getName() : $field;

        return sprintf(
            '%s__%s__%s__%s',
            $this->name,
            str_replace("\\", "-", $record->ClassName),
            $record->ID,
            $name
        );
    }

    public function saveInto(DataObjectInterface $record)
    {
        $allItems = array();
        foreach ($this->children as $field) {
            $fieldname = $field->getName();
            if (strpos($fieldname, '__') > 0) {
                $bits = array_reverse(explode('__', $fieldname));
                if (count($bits) > 3) {
                    list($dataFieldName, $id, $classname) = $bits;
                    $classname = str_replace('-', "\\", $classname);

                    if (!isset($allItems["$classname-$id"])) {
                        $item = $this->records->filter(array('ClassName' => $classname, 'ID' => $id))->first();
                        $allItems["$classname-$id"] = $item;
                    }
                    $item = $allItems["$classname-$id"];
                    if ($item) {
                        // we need to clone the field, otherwise the field object
                        // itself gets modified. The field object itself exists
                        // in a cached list elsewhere, and changing the name here
                        // would otherwise break things
                        if ($field) {
                            $cloned = clone $field;
                            $cloned->setName($dataFieldName);
                            $cloned->saveInto($item);
                        }
                    }
                }
            }
        }

        foreach ($allItems as $item) {
            $item->write();
        }

        parent::saveInto($record);
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function FieldHolder($properties = array())
    {
        if ($this->useToggles) {
            return $this->tabs;
        }

        return $this->children;
    }
}
