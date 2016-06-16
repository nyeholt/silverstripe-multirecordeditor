<?php

class MultiRecordEditingSubRecordField extends CompositeField {
    /**
     * @var MultiRecordEditingField
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
    private $toggleCompositeField;

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
     * @return MultiRecordEditingSubRecordField
     */
    public function setParent(MultiRecordEditingField $parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return MultiRecordEditingField
     */ 
    public function getParent() {
        return $this->parent;
    }

    /**
     * @return MultiRecordEditingSubRecordField
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

    /**
     * @return boolean
     */
    public function getCanSort() {
        return $this->parent->getCanSort();
    }

    /**
     * @return string
     */
    public function getFieldID() {
        return $this->parent->getFieldID($this->record);
    }

    /**
     * @return ToggleCompositeField
     */
    public function ToggleCompositeField() {
        if (!$this->toggleCompositeField) {
            $title = $this->Title();
            $this->toggleCompositeField = ToggleCompositeField::create('CompositeHeader'.$this->getFieldID(), $title, $this->getChildren());
        }
        return $this->toggleCompositeField;
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
                throw new LogicException(__CLASS__.'::'.__FUNCTION__.': Invalid $this->record property. Ensure it\'s set by MultiRecordEditingField.');
            }
            $parent = $this->getParent();
            if (!$parent || !is_object($parent) || !$parent instanceof MultiRecordEditingField) {
                throw new LogicException(__CLASS__.'::'.__FUNCTION__.': Invalid $this->parent property. Ensure it\'s set by MultiRecordEditingField.');
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
        $this->prepareForRender();
        return $this->Field($properties);
    }

    public function Field($properties = array()) {
        $this->prepareForRender();
        return parent::Field($properties);
    }
}