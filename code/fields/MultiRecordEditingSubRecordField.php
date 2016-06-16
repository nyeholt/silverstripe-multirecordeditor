<?php

class MultiRecordEditingSubRecordField extends CompositeField {
    /**
     * @var MultiRecordEditingField
     */
    protected $parent;

    /**
     * @var DataObject
     */
    protected $record;

    // todo(jake): document
    protected $toggleCompositeField;

    /** 
     * @var boolean
     */
    protected $preparedForRender = false;

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
            $field = $this->ToggleCompositeField();
            if ($field)
            {
                $field->setForm($this->form);
                $field->setChildren($this->children);
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