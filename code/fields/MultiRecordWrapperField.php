<?php

/*
    NOTE(Jake): Attempted to make a generic wrapper to fix actions on forms naturally but
                templates dont have access to the wrapper functions, rendering this useless.
*/

/*class MultiRecordWrapperField extends FormField
{
    public $originalField;

    public $multiRecordAction = '';

    public function __construct(FormField $originalField) {
        $this->originalField = $originalField;
    }

    public function __call($method, $arguments) {
        return call_user_func_array(array($this->originalField, $method), $arguments);
    }

    public function __get($property) {
        if (isset($this->originalField->{$property}))
        {
            return $this->originalField->{$property};
        }
        return parent::__get($property);
    }

    public function Link($action = null) {
        if ($this->multiRecordAction) {
            return $this->originalField->form->FormAction().'/field/'.$this->multiRecordAction.'/'.$action;
        }
        return $this->originalField->Link($action);
    }

    // Add wrappers around every FormField function
    protected function setupDefaultClasses() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function ID() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function HolderID() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function getTemplateHelper() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args());}
    public function getName() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function Message() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function MessageType() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function Value() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function saveInto(DataObjectInterface $record) { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function dataValue() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function Title() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function setTitle($title) { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
    public function RightTitle() { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }

    public function setForm($form) { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }

    public function FieldHolder($properties = array()) { 
        return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); 
    }
    public function Field($properties = array()) { return call_user_func_array(array($this->originalField, __FUNCTION__), func_get_args()); }
}
*/