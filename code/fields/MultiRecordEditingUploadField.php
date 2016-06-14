<?php

/**
 * Simple wrapper class to allow actions to feed through MultiRecordEditingField
 */
class MultiRecordEditingUploadField extends UploadField {
    /**
     * Override with a new action.
     *
     * @var string
     */
    public $multiRecordEditingFieldAction = '';

    /**
     * @return MultiRecordEditingUploadField
     */ 
    public static function cast(UploadField $field) {
        $castCopy = MultiRecordEditingUploadField::create($field->getName(), $field->Title());
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