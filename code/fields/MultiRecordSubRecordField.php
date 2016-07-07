<?php

/**
 * Keep track of the record editing and the MultiRecordField
 * this is attached to.
 */
class MultiRecordSubRecordField extends CompositeField {
    /**
     * @var MultiRecordField
     */
    protected $parent = null;

    /**
     * @var DataObject
     */
    protected $record = null;

    /** 
     * @var boolean
     */
    protected $preparedForRender = false;

    /**
     * @var ToggleCompositeField
     */
    private $toggleCompositeField = null;

    /**
     * @param string $name
     * @param string $title
     * @param array|FieldList $children
     */
    public function __construct($name, $title, $children = null) {
        $this->name = $name;
        $this->title = $title;
        $this->children = ($children) ? $children : new FieldList();

        parent::__construct($children);
    }

    /**
     * @return MultiRecordSubRecordField
     */
    public function setParent(MultiRecordField $parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return MultiRecordField
     */ 
    public function getParent() {
        return $this->parent;
    }

    /**
     * @return MultiRecordSubRecordField
     */
    public function setRecord($record)
    {
        $this->record = $record;
        return $this;
    }

    /**
     * @return DataObject
     */ 
    public function getRecord() {
        return $this->record;
    }

    public function getDataID() {
        $record = ($this->getRecord());
        if ($record && $record->ID) {
            return $record->ID;
        }
        if ($record && $record->MultiRecordField_NewID) {
            return $record->MultiRecordField_NewID;
        }
        $parent = $this->getParent();
        if ($parent) {
            return $parent->getFieldID(0);
        }
        throw new Exception('Unable to determine DataID');
    }

    /**
     * @return string
     */
    public function getName() {
        $result = parent::getName();
        $result = rtrim($result, '__');
        return $result;
    }

    /**
     * @return boolean
     */
    public function getCanSort() {
        return (!$this->isReadonly() && $this->parent->getCanSort());
    }

    /**
     * @return string
     */
    public function getFieldID() {
        return $this->parent->getFieldID($this->record);
    }

    /**
     * @return FieldList
     */
    public function Actions() {
        $actions = new FieldList;

        $record = $this->getRecord();
        $showDeleteButton = (!$record || !$record->exists() || $record->canDelete());

        // Setup delete button
        if ($showDeleteButton)
        {
            // Add delete button
            $deleteButton = FormAction::create('delete', 'Delete');
            $deleteButton->setUseButtonTag(true);
            $deleteButton->addExtraClass('multirecordfield-delete-button js-multirecordfield-delete');
            $actions->push($deleteButton);

            // Add undo button (you only need undo with delete)
            $deleteButton = FormAction::create('undodelete', 'Restore');
            $deleteButton->setUseButtonTag(true);
            $deleteButton->addExtraClass('multirecordfield-undo-button js-multirecordfield-undo');
            $actions->push($deleteButton);
        }

        // Update action fields with button classes
        $parent = $this->getParent();
        if ($parent) {
            $parent->applyButtonClasses($actions);
        }

        return $actions;
    }

    /**
     * @return ToggleCompositeField
     */
    public function ToggleCompositeField() {
        if ($this->toggleCompositeField === null) {
            $title = $this->Title();
            $record = $this->getRecord();
            $recordClass = $record->class;
            $field = ToggleCompositeField::create('CompositeHeader_'.$recordClass.$this->getFieldID(), $title, $this->getChildren());
            $field->addExtraClass('multirecordfield-togglecompositefield js-multirecordfield-togglecompositefield');
            $this->toggleCompositeField = $field;
        }
        return $this->toggleCompositeField;
    }

    /**
     * Returns a read-only version of this field.
     *
     * @return FormField
     */
    public function performReadonlyTransformation() {
        $resultField = MultiRecordSubRecordField_Readonly::create($this->name, $this->title, $this->list);
        foreach (get_object_vars($this) as $property => $value)
        {
            $resultField->$property = $value;
        }
        $resultField->readonly = true;
        foreach ($this->children as $field)
        {
            $this->children->replaceField($field->getName(), $field->performReadonlyTransformation());
        }
        return $resultField;
    }

    /** 
     * Setup sub-field(s) just before rendering
     *
     * @var boolean
     */
    protected function prepareForRender() {
        if (!$this->preparedForRender) {
            $this->preparedForRender = true;

            // Check for user/dev errors
            $record = $this->getRecord();
            if (!$record || !is_object($record)) {
                throw new LogicException(__CLASS__.'::'.__FUNCTION__.': Invalid $this->record property. Ensure it\'s set by MultiRecordField.');
            }
            $parent = $this->getParent();
            if (!$parent || !is_object($parent) || !$parent instanceof MultiRecordField) {
                throw new LogicException(__CLASS__.'::'.__FUNCTION__.': Invalid $this->parent property. Ensure it\'s set by MultiRecordField.');
            }

            // Setup actions
            $actions = $this->Actions();
            if ($actions && $actions->count())
            {
                foreach ($actions as $action) 
                {
                    $this->push($action);
                }
            }

            // Prepare ToggleCompositeField
            $field = $this->ToggleCompositeField();
            if ($field)
            {
                $field->setForm($this->form);
                $field->setStartClosed($this->getRecord()->exists());
            }
            return true;
        }
        return false;
    }

    public function FieldHolder($properties = array()) {
        return $this->Field($properties);
    }

    public function Field($properties = array()) {
        $this->prepareForRender();
        return parent::Field($properties);
    }
}

class MultiRecordSubRecordField_Readonly extends MultiRecordSubRecordField {
    protected $readonly = true;

    /**
     * @return FieldList
     */
    public function Actions() {
        return new FieldList();
    }
}