<?php

if (!class_exists('FileAttachmentField')) {
    return;
}

/**
 * Support for FileAttachmentField from Unclecheese's Dropzone module.
 * Only supports ~1.2.x at time of writing (20/06/2016)
 */ 
class MultiRecordFileAttachmentField extends FileAttachmentField {
    private static $allowed_actions = array(
        'upload',
    );

    /**
     * Override with a new action.
     *
     * @var string
     */
    public $multiRecordAction = '';

    /**
     * The name used in the SS_HTTPRequest
     *
     * @var string
     */
    public $MultiRecordEditing_Name = '';

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
     * Saves the field into a record
     * @param  DataObjectInterface $record
     * @return FileAttachmentField
     */
    public function saveInto(DataObjectInterface $record) {
        // NOTE(Jake): Calling this so it saves into the $record using the $this->getName()
        //             to determine the relation name.
        $result = parent::saveInto($record);

        if ($this->MultiRecordEditing_Name) 
        {
            // NOTE(Jake): Handle deletions by using the original sent name here, 
            //             ie. Use 'ElementArea__MultiRecordField__ElementGallery__33__Image' not 'Image'. 
            $deletions = Controller::curr()->getRequest()->postVar('__deletion__'.$this->MultiRecordEditing_Name);

            if($deletions) {
                $deletions = (array)$deletions;
                foreach($deletions as $id) {
                    $this->deleteFileByID($id);
                }
            }
        }

        return $result;
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
     * @return MultiRecordFileAttachmentField
     */ 
    public static function cast(FileAttachmentField $field) {
        $castCopy = MultiRecordFileAttachmentField::create($field->getName(), $field->Title(), $field->Value(), $field->getForm());
        foreach (get_object_vars($field) as $property => $value)
        {
            $castCopy->$property = $value;
        }
        return $castCopy;
    }

    public function Link($action = null) {
        if ($this->multiRecordAction) {
            return $this->form->FormAction().'/field/'.$this->multiRecordAction.'/'.$action;
        }
        return parent::Link($action);
    }
}