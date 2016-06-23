<?php

class MultiRecordUploadField extends UploadField {
    /**
     * @var array
     */
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
     * Action to handle upload of a single file
     *
     * @param SS_HTTPRequest $request
     * @return SS_HTTPResponse
     * @return SS_HTTPResponse
     */
    public function upload(SS_HTTPRequest $request) {
        // Find the first set of upload data that looks like it came from an
        // UploadField.
        // NOTE(Jake): There is only the SecurityID and the 'upload' data as this is done
        //             in it's own AJAX request, not sent along with all the other data.
        foreach ($request->postVars() as $key => $value)
        {
            // todo(Jake): Test SS 3.1 compatibility
            // NOTE(Jake): Only tested in SS 3.2, UploadField::upload data may not look like this in 3.1...
            if ($value && isset($value['name']) && isset($value['type']) && isset($value['tmp_name']))
            {
                // NOTE(Jake): UploadField requires this FormField to be the correct name so it can retrieve
                //             the file information from postVar. So detect the first set of data that seems
                //             like upload info and use it.
                $this->setName($key);
                break;
            }
        }
        return parent::upload($request);
    }

    /**
     * @return MultiRecordUploadField
     */ 
    public static function cast(UploadField $field) {
        $castCopy = MultiRecordUploadField::create($field->getName(), $field->Title());
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