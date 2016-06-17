<?php

if (!class_exists('FileAttachmentField')) {
    return;
}

class MultiRecordEditingFileAttachmentField extends FileAttachmentField {
    private static $allowed_actions = array(
        'upload',
    );

    /**
     * Override with a new action.
     *
     * @var string
     */
    public $multiRecordEditingFieldAction = '';

    /**
     * Action to handle upload of a single file
     *
     * @param SS_HTTPRequest $request
     * @return SS_HTTPResponse
     * @return SS_HTTPResponse
     */
    public function upload(SS_HTTPRequest $request) {
        return parent::upload($request);
    }

    /**
     * Set the record that this form field is editing
     * @return DataObject
     */
    public function setRecord($record) {
        $this->record = $record;
        return $this;
    }

    /**
     * Get the record that this form field is editing
     * @return DataObject
     */
    public function getRecord() {
        return $this->record;
    }

    /**
     * @return MultiRecordEditingFileAttachmentField
     */ 
    public static function cast(FileAttachmentField $field) {
        $castCopy = MultiRecordEditingFileAttachmentField::create($field->getName(), $field->Title(), $field->Value(), $field->getForm());
        foreach (get_object_vars($field) as $property => $value)
        {
            $castCopy->$property = $value;
        }
        return $castCopy;
    }

    public function Link($action = null) {
        if ($this->multiRecordEditingFieldAction) {
            return $this->form->FormAction().'/field/'.$this->multiRecordEditingFieldAction.'/'.$action;
        }
        return parent::Link($action);
    }
}