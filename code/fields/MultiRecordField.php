<?php

/**
 * For tracking field (sent/expanded names) and values in
 * 'saveInto'
 */
class MultiRecordFieldData {
    /**
     * Keep the original requested name for the field as FileAttachmentField
     * needs it for processing deleted items.
     *
     * @var string
     */
    public $requestName;

    /** 
     * @var mixed
     */
    public $value;
}

/**
 * @author Jake Bentvelzen
 */
class MultiRecordField extends FormField {
    /**
     * Invalid sort value. Value should be replaced by JavaScript on
     * new records without a sort value.
     *
     * @var int
     */
    const SORT_INVALID = 0;

    /**
     * Structure for when 'saveInto' is tracking
     * new records that need to be saved.
     */
    const NEW_RECORD = 0;
    const NEW_LIST = 1;

    /**
     * Set variables or call functions on certain fields underneath this field.
     * ie. Change rows to 6 for HtmlEditorField so it takes less space.
     *
     * @config
     * @var array
     */
    private static $default_config = array(
        /*'HtmlEditorField' => array(
            'functions' => array(
                'setRows' => 6,
            ),
        ),*/
    );

    /**
     * Classes to apply to every FormAction field.
     * (ie. <button> or <input type="submit" />)
     *
     * @config
     * @var string
     */
    private static $default_button_classes = '';

    /** 
     * Enable workaround for ListboxField bug in 'framework' 3.3 and below.
     * When disabled, an exception will be thrown if that bug is detected.
     *
     * https://github.com/silverstripe/silverstripe-framework/pull/5775
     *
     * @config
     * @var boolean
     */
    private static $enable_patch_5775 = false;

    /**
     * Defaults to default_config if not set.
     *
     * @var array
     */
    protected $config = null;

    /**
     * The list object passed into the object.
     * 
     * @var SS_List
     */
    protected $list;

    /**
     * @var FieldList
     */
    protected $actions = null;

    /**
     * Field to use for the ToggleCompositeField's heading/title
     *
     * @var string
     */
    protected $titleField = '';

    /**
     * Override the field function to call on the record.
     *
     * @var function|string
     */
    protected $fieldsFunction = '';

    /**
     * Whether to fallback on 'getDefaultFieldsFunction' if the $fieldsFunction
     * is a string and doesn't exist on the record.
     *
     * @var boolean
     */
    protected $fieldsFunctionFallback = false;

    /**
     * Class name of the DataObject that the GridField will display.
     *
     * Defaults to the value of $this->list->dataClass.
     *
     * @var string
     */
    protected $modelClassName = '';

    /**
     * Whether to override html editor heights
     *
     * @var int
     */
    protected $htmlEditorHeight = 6;

    /**
     * The field name to sort by.
     *
     * @var string|array
     */
    protected $sortFieldName = null;

    /**
     * Should we use toggle Composites in layout ? 
     *
     * @var boolean
     */
    protected $useToggles = true;

    /** 
     * @var boolean
     */
    protected $preparedForRender = false;

    /**
     * @var boolean
     */
    protected $canAddInline = true;

    /**
     * @var FieldList
     */
    protected $children, $tabs;

    /**
     * If null/false, fallback to default_button_classes config
     *
     * @var string|null
     */
    protected $buttonClasses = null;

    /**
     * @var array List of additional CSS classes for the form tag.
     */
    protected $extraClasses = array();

    /**
     * How nested inside other MultiRecordField's this field is.
     *
     * @var int
     */
    protected $depth = 1;

    public function __construct($name, $title = null, SS_List $list = null)
    {
        parent::__construct($name, $title);

        $this->children = FieldList::create();
        $this->list = $list;
    }

    /**
     * 
     */
    public function handleAddInline(SS_HTTPRequest $request) {
        // Force reset
        $this->children = FieldList::create();

        // Get passed arguments
        // todo(Jake): Change '->remaining' to '->shift(4)' and test.
        //             remove other ->shift things.
        $dirParts = explode('/', $request->remaining());
        $class = isset($dirParts[0]) ? $dirParts[0] : '';
        if (!$class)
        {
            return $this->httpError(400, 'No ClassName was supplied.');
        }

        $modelClassNames = $this->getModelClasses();
        if (!isset($modelClassNames[$class]))
        {
            return $this->httpError(400, 'Invalid ClassName "'.$class.'" was supplied.');
        }

        // Determine sub field action (if executing one)
        $isSubFieldAction = (isset($dirParts[1]));
        $recordIDOrNew = (isset($dirParts[1]) && $dirParts[1]) ? $dirParts[1] : null;

        if ($recordIDOrNew === null || $recordIDOrNew === 'new')
        {
            $record = $class::create();
            if (!$record->canCreate(Member::currentUser()))
            {
                return $this->httpError(400, 'Invalid permissions. Current user (#'.Member::currentUserID().') cannot create "'.$class.'" class type.');
            }
        }
        else
        {
            $recordIDOrNew = (int)$recordIDOrNew;
            if (!$recordIDOrNew)
            {
                return $this->httpError(400, 'Malformed record ID in sub-field action was supplied ('.$class.' #'.$recordIDOrNew.').');
            }
            $record = $class::get()->byID($recordIDOrNew);
            if (!$record->canEdit(Member::currentUser()))
            {
                return $this->httpError(400, 'Invalid permissions. Current user (#'.Member::currentUserID().') cannot edit "'.$class.'" #'.$recordIDOrNew.' class type.');
            }
        }

        // Check if sub-field exists on requested record (can request new record with 'new')
        $fields = $this->getRecordDataFields($record);
        $dataFields = $fields->dataFields();

        // 
        $isValidSubFieldAction = (isset($dirParts[2]) && $dirParts[2] === 'field') ? true : false;
        $subField = null;
        if ($isSubFieldAction) {
            $subFieldName = (isset($dirParts[3]) && $dirParts[3]) ? $dirParts[3] : '';
            if (!$subFieldName || !isset($dataFields[$subFieldName]))
            {
                return $this->httpError(400, 'Invalid sub-field was supplied ('.$class.'::'.$subFieldName.').');
            }
            $subField = $dataFields[$subFieldName];
        }

        $this->applyUniqueFieldNames($fields, $record);

        // If set a sub-field, execute its action instead.
        if ($isSubFieldAction)
        {
            if ($isValidSubFieldAction && $subField)
            {
                // Consume so Silverstripe handles the actions naturally.
                $request->shift(); // $ClassName
                $request->shift(); // $ID ('new' or '1')
                $request->shift(); // field
                $request->shift(); // $SubFieldName
                return $subField;
            }
            return $this->httpError(400, 'Invalid sub-field action on '.__CLASS__.'::'.__FUNCTION__);
        }

        // Allow fields to render, 
        $this->children = $fields;

        // Remove all actions
        $actions = $this->Actions();
        foreach ($actions as $action) {
            $actions->remove($action);
        }

        return $this->renderWith(array($this->class.'_addinline', __CLASS__.'_addinline'));
    }

    public function handleRequest(SS_HTTPRequest $request, DataModel $model) {
        if ($request->match('addinlinerecord', true)) {
            // NOTE(Jake): Handling here as I'm not sure how to do a url_handler that allows
            //             infinite parameters after 'addinlinerecord'
            $result = $this->handleAddInline($request);
            if ($result && is_object($result) && $result instanceof RequestHandler)
            {
                // NOTE(Jake): Logic copied from parent::handleRequest()
                $returnValue = $result->handleRequest($request, $model);
                if($returnValue && is_array($returnValue)) { 
                    $returnValue = $this->customise($returnValue);
                }
                return $returnValue;
            }
            // NOTE(Jake): Consume all remaining parts so that 'RequestHandler::handleRequest'
            //             doesn't hit an error. (Use Case: Getting an error with a GridField::handleRequest)
            // NOTE(Jake): THis is probably due to just CLASSNAME not being consumed/shifted in 'addinlinerecord'
            //             but cbf changing and re-testing everything.
            $dirParts = explode('/', $request->remaining());
            foreach ($dirParts as $dirPart)
            {
                $request->shift();
            }
            return $result;
        }

        $result = parent::handleRequest($request, $model);
        return $result;
    }

    /**
     * @param boolean $value
     * @return \MultiRecordField
     */
    public function setUseToggles($value)
    {
        $this->useToggles = $value;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUseToggles()
    {
        return $this->useToggles;
    }

    /**
     * @param boolean $value
     * @return \MultiRecordField
     */
    public function setCanAddInline($value) {
        $value = $this->canAddInline;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getCanAddInline() {
        return $this->canAddInline;
    }


    /**
     * @return string
     */
    public function getButtonClasses() {
        $result = $this->buttonClasses;
        if ($result === null || $result === false)
        {
            return $this->stat('default_button_classes');
        }
        return $result;
    }

    /**
     * Set the classes to be applied on each FormAction field.
     * (ie. <button> or <input type="submit" />)
     *
     * @return \MultiRecordField
     */
    public function setButtonClasses($classes) {
        $this->buttonClasses = $classes;
        return $this;
    }

    /**
     * Apply button classes to a fieldlist of actions
     *
    * @return \MultiRecordField
     */
    public function applyButtonClasses(FieldList $actions) {
        $buttonClasses = $this->getButtonClasses();
        if ($buttonClasses && $actions)
        {
            foreach ($actions as $actionField)
            {
                if ($actionField instanceof FormAction)
                {
                    $actionField->addExtraClass($buttonClasses);
                }
            }
        }
        return $this;
    }

    /**
     * @param array value
     * @param array data Passed from Form::loadDataFrom for composite fields to act on data
     * @return \MultiRecordField
     */
    public function setValue($value, $formData = array()) {
        if (!$value && $formData && is_array($formData))
        {
            // NOTE(Jake): The call stack is: 
            //                  $field->setValue($val, $formData);
            //                  $form->loadDataFrom($data)
            //                  $form->httpSubmission($request);
            if ($formData)
            {
                $relation_class_id_field = array();
                $relationFieldName = $this->getName();

                foreach ($formData as $name => $value)
                {
                    // If fieldName is the -very- first part of the string
                    // NOTE(Jake): Check is required here as we're pulling straight from $request
                    if (strpos($name, $relationFieldName) === 0)
                    {
                        static $FIELD_PARAMETERS_SIZE = 5;

                        $fieldParameters = explode('__', $name);
                        $fieldParametersCount = count($fieldParameters);
                        if ($fieldParametersCount < $FIELD_PARAMETERS_SIZE) {
                            // You expect a name like 'ElementArea__MultiRecordField__ElementGallery__new_1__Title'
                            // So ensure 5 parameters exist in the name, otherwise continue.
                            continue;
                        }
                        $signature = $fieldParameters[1];
                        if ($signature !== 'MultiRecordField')
                        {
                            return $this->httpError(400, 'Invalid signature in "MultiRecordField". Malformed MultiRecordField sub-field or hack attempt.');
                        }

                        $parentFieldName = $fieldParameters[0];
                        $class = $fieldParameters[2];
                        $new_id = $fieldParameters[3];
                        $fieldName = $fieldParameters[4];

                        //
                        $fieldData = new MultiRecordFieldData;
                        $fieldData->requestName = $name;
                        $fieldData->value = $value;
                        if ($fieldParametersCount == $FIELD_PARAMETERS_SIZE)
                        {
                            // 1st Nest Level
                            $relation_class_id_field[$parentFieldName][$class][$new_id][$fieldName] = $fieldData; 
                        }
                        else
                        {
                            // 2nd, 3rd, nth Nest Level
                            $relationArray = &$relation_class_id_field;
                            for ($i = 0; $i < $fieldParametersCount - 1; $i += $FIELD_PARAMETERS_SIZE - 1)
                            {
                                $parentFieldName = $fieldParameters[$i];
                                $signature = $fieldParameters[$i+1];
                                $class = $fieldParameters[$i+2];
                                $new_id = $fieldParameters[$i+3];
                                $fieldName = $fieldParameters[$i+4];
                                if (!isset($relationArray[$parentFieldName][$class][$new_id])) {
                                    $relationArray[$parentFieldName][$class][$new_id] = array();
                                }
                                $relationArray = &$relationArray[$parentFieldName][$class][$new_id];
                            }
                            $relationArray[$fieldName] = $fieldData; 
                            unset($relationArray);
                        }
                    }
                }

                // Set value
                $this->value = reset($relation_class_id_field);
                return $this;
            }
        }
        $this->value = $value;
        return $this;
    }

    /**
     * @return \MultiRecordField
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @return \MultiRecordField
     */
    public function setConfig($config) {
        if ($config && $config instanceof GridFieldConfig) {
            // NOTE(Jake): Stubbed by design so developers can switch between GridField and this class quickly for test/dev purposes.
            return $this;
        }
        // todo(Jake): Improve API to allow multiple params (ie. setConfigFunction('HtmlEditorField', 'setRows', 6))
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the first model class from list.
     * This function exists to be identical to the GridField function so developers can
     * quickly switch between the two.
     *
     * @return string
     */
    public function getModelClass() {
        return reset($this->modelClassNames);
    }

    /**
     * Set one model class. 
     * This function exists to be identical to the GridField function so developers can
     * quickly switch between the two.
     *
     * @param string $modelClass
     * @return \MultiRecordField
     */
    public function setModelClass($modelClass) {
        if (is_array($modelClass))
        {
            throw new Exception(__CLASS__.'::'.__FUNCTION__.': Only accepts singular value (not array). Use setModelClasses() instead.');
        }
        return $this->setModelClasses($modelClass);
    }

    /**
     * If array, can be formatted like so:
     * array('MyClass', 'MyOtherClass')
     * -or-
     * array('MyClass' => 'Nice Name 1', 'MyOtherClass' => 'Nice Name 2')
     *
     * The classes provided must all extend the same parent.
     *
     * @param array|string $modelClassName
     *
     * @return \MultiRecordField
     */
    public function setModelClasses($modelClassNames) {
        if (is_array($modelClassNames)) {
            $this->modelClassNames = $modelClassNames;
        } else {
            $this->modelClassNames = array($modelClassNames);
        }

        $this->modelClassNames = static::convert_to_associative($this->modelClassNames);

        return $this;
    }

    /**
     * @return array
     */
    public function getModelClasses() {
        if($this->modelClassNames) {
            return $this->modelClassNames;
        }

        if($this->list && method_exists($this->list, 'dataClass')) {
            $class = $this->list->dataClass();

            if ($class) 
            {
                if(!is_array($class)) {
                    $class = array($class);
                }
                return static::convert_to_associative($class);
            }
        }

        return array();
    }

    /**
     * @return array
     *
     * @throws LogicException
     */
    public function getModelClassesOrThrowExceptionIfEmpty() {
        $modelClasses = $this->getModelClasses();
        if (!$modelClasses) 
        {
            throw new LogicException(__CLASS__.' doesn\'t have any modelClasses set, so it doesn\'t know what class types can be added inline.');
        }
        return $modelClasses;
    }

    /**
     * Convert regular array to associative, making the key
     * the classname and the value the pretty/front-facing name.
     *
     * @return array
     */
    public static function convert_to_associative($modelClasses)
    {
        $source = array();
        if (isset($modelClasses[0]))
        {
            // If regular array, fallback to singular name
            foreach ($modelClasses as $modelClass) {
                $source[$modelClass] = singleton($modelClass)->singular_name();
            }
        }
        else
        {
            // If associative/hashmap, make the key the classname
            foreach ($modelClasses as $modelClass => $niceName) {
                $source[$modelClass] = $niceName;
            }
        }
        return $source;
    }

    /**
     *  @return int|string
     */
    public function getFieldID($record) {
        if ($record && $record->ID) {
            return (int)$record->ID;
        }
        if ($record && $record->MultiRecordField_NewID) {
            // Allow setting 'MultiRecordField_NewID' to say 'new_1' or 'new_2'
            // to restore fields that failed validation.
            return $record->MultiRecordField_NewID;
        }
        // NOTE(Jake): Not '{%=o.multirecordediting.id%}' with tmpl.js because SS strips '{', '}' and replaces '.' with '-'
        return 'o-multirecordediting-'.$this->depth.'-id';
    }

    /**
     * @return SS_List
     */
    public function getList() {
        return $this->list;
    }

    /**
     * @param SS_List $list
     * @return \MultiRecordField
     */
    public function setList(SS_List $list) {
        $this->list = $list;
        return $this;
    }

    /**
     * @param Form $form
     * @return \MultiRecordField
     */
    public function setForm($form)
    {
        parent::setForm($form);

        foreach ($this->children as $child) {
            $child->setForm($form);
        }
        return $this;
    }

    /**
     * @return boolean
     */
    public function getCanSort() {
        return (!$this->isReadonly() && $this->getSortFieldName());
    }

    /**
     * Get the field to use for the ToggleCompositeField's heading/title
     *
     * @return string
     */
    public function getTitleField() {
        return $this->titleField;
    }

    /**
     * Set the field to use for the ToggleCompositeField's heading/title
     *
     * @return \MultiRecordField
     */
    public function setTitleField($fieldName) {
        $this->titleField = $fieldName;
        return $this;
    }

    /**
     * Get the default function to call on the record if
     * $this->fieldsFunction isn't set.
     * 
     * @return string
     */
    public function getDefaultFieldsFunction(DataObjectInterface $record) {
        if (method_exists($record, 'getMultiRecordFields') 
            || (method_exists($record, 'hasMethod') && $record->hasMethod('getMultiRecordFields'))) 
        {
            return 'getMultiRecordFields';
        }
        else
        {
            return 'getCMSFields';
        }
    }

    /**
     * Get closure or string of the function to call for getting record
     * fields.
     * 
     * @return function|string
     */
    public function getFieldsFunction() {
        return $this->fieldsFunction;
    }

    /**
     * Set the function to call on the $record for determining what fields to show.
     *
     * If string, then set the method to call on the record to get fields.
     * If closure, then call the method for the fields with $record as the first parameter.
     *
     * @param string|function $functionOrFunctionName 
     * @param boolean $fallback If true, fallback to using 'getMultiRecordFields' and then fallback to 'getCMSFields'
     * @return MultiRecordField
     */
    public function setFieldsFunction($functionOrFunctionName, $fallback = false) {
        $this->fieldsFunction = $functionOrFunctionName;
        $this->fieldsFunctionFallback = $fallback;
        return $this;
    }

    /**
     * @return FieldList|null
     */
    public function getRecordDataFields(DataObjectInterface $record) {
        $fieldsFunction = $this->getFieldsFunction();
        if (!$fieldsFunction)
        {
            $fieldsFunction = $this->getDefaultFieldsFunction($record);
        }

        $fields = null;
        if (is_callable($fieldsFunction)) 
        {
            $fields = $fieldsFunction($record);
        } 
        else
        {
            if (method_exists($record, $fieldsFunction) 
                || (method_exists($record, 'hasMethod') && $record->hasMethod($fieldsFunction))) 
            {
                $fields = $record->$fieldsFunction();
            }
            else if ($this->fieldsFunctionFallback)
            {
                $fieldsFunction = $this->getDefaultFieldsFunction($record);
                $fields = $record->$fieldsFunction();
            }
            else
            {
                throw new Exception($record->class.'::'.$fieldsFunction.' function does not exist.');
            }
        } 
        if (!$fields || !$fields instanceof FieldList)
        {
            throw new Exception('Function callback on '.__CLASS__.' must return a FieldList.');
        }
        $record->extend('updateMultiEditFields', $fields);
        $fields = $fields->dataFields();
        if (!$fields) {
            $errorMessage = __CLASS__.' is missing fields for record #'.$record->ID.' on class "'.$record->class.'".';
            if (is_callable($fieldsFunction)) {
                throw new Exception($errorMessage.'. This is due to the closure set with "setFieldsFunction" not returning a populated FieldList.');
            }
            throw new Exception($errorMessage.'. This is due '.$record->class.'::'.$fieldsFunction.' not returning a populated FieldList.');
        }

        // 
        $recordExists = $record->exists();

        // Set value from record if it exists or if re-loading data after failed form validation
        $recordShouldSetValue = ($recordExists || $record->MultiRecordField_NewID);

        // Setup sort field
        $sortFieldName = $this->getSortFieldName();
        if ($sortFieldName)
        {
            $sortField = isset($fields[$sortFieldName]) ? $fields[$sortFieldName] : null;
            if ($sortField && !$sortField instanceof HiddenField)
            {
                throw new Exception('Cannot utilize drag and drop sort functionality if the sort field is explicitly used on form. Suggestion: $fields->removeByName("'.$sortFieldName.'") in '.$record->class.'::'.$fieldsFunction.'().');
            }
            if (!$sortField)
            {
                $sortValue = ($recordShouldSetValue) ? $record->$sortFieldName : self::SORT_INVALID;
                $sortField = HiddenField::create($sortFieldName);
                if ($sortField instanceof HiddenField) {
                    $sortField->setAttribute('value', $sortValue);
                } else {
                    $sortField->setValue($sortValue);
                }
                $sortField->setAttribute('data-ignore-delete-check', 1);
                // NOTE(Jake): Uses array_merge() to prepend the sort field in the $fields associative array.
                //             The sort field is prepended so jQuery.find('.js-multirecordfield-sort-field').first()
                //             finds the related sort field to this, rather than a sort field nested deeply in other
                //             MultiRecordField's.
                $fields = array_merge(array(
                    $sortFieldName => $sortField
                ), $fields);
            }
            $sortField->addExtraClass('js-multirecordfield-sort-field');
        }

        // Set heading (ie. 'My Record (Draft)')
        $titleFieldName = $this->getTitleField();
        $status = '';
        if (!$titleFieldName)
        {
            $recordSectionTitle = $record->MultiRecordEditingTitle;
            if (!$recordSectionTitle)
            {
                $recordSectionTitle = $record->Title;
                $status = ($recordExists) ? $record->CMSPublishedState : 'New';
            }
        }
        else
        {
            $recordSectionTitle = $record->$titleFieldName;
        }
        if (!$recordSectionTitle) {
            // NOTE(Jake): Ensures no title'd ToggleCompositeField's have a proper height.
            $recordSectionTitle = '&nbsp;';
        }
        $recordSectionTitle .= ' <span class="js-multirecordfield-title-status">';
        $recordSectionTitle .= ($status) ? '('.$status.')' : '';
        $recordSectionTitle .= '</span>';

        // Add heading field / Togglable composite field with heading
        $subRecordField = MultiRecordSubRecordField::create('', $recordSectionTitle, null);
        $subRecordField->setParent($this);
        $subRecordField->setRecord($record);
        if ($this->readonly) {
            $subRecordField = $subRecordField->performReadonlyTransformation();
        }

        // Modify sub-fields to work properly with this field
        $currentFieldListModifying = $subRecordField;
        foreach ($fields as $field)
        {
            $fieldName = $field->getName();

            if ($recordShouldSetValue)
            {
                if (isset($record->$fieldName)
                    || $record->hasMethod($fieldName)
                    || ($record->hasMethod('hasField') && $record->hasField($fieldName)))
                {
                    $val = $record->__get($fieldName);
                    $field->setValue($val, $record);
                }
            }

            if ($field instanceof MultiRecordField) {
                $field->depth = $this->depth + 1;
                $action = $this->getActionURL($field, $record);
                $field->setAttribute('data-action', $action);
                // NOTE(Jake): Unclear at time of writing (17-06-2016) if nested MultiRecordField should
                //             inherit certain settings or not. Might add flag like 'setRecursiveOptions' later
                //             or something.
                $field->setFieldsFunction($this->getFieldsFunction(), $this->fieldsFunctionFallback);
                //$field->setTitleField($this->getTitleField());
            }
            else
            {
                $config = $this->getConfig();
                if ($config === null)
                {
                    $config = $this->config()->default_config;
                }
                // todo(Jake): Make it walk class hierarchy so that things that extend say 'HtmlEditorField'
                //             will also get the config. Make the '*HtmlEditorField' denote that it's only
                //             for that class, sub-classes.
                if (isset($config[$field->class]))
                {
                    $fieldConfig = $config[$field->class];
                    $functionCalls = isset($fieldConfig['functions']) ? $fieldConfig['functions'] : array();
                    if ($functionCalls)
                    {
                        foreach ($functionCalls as $methodName => $arguments)
                        {
                            $arguments = (array)$arguments;
                            call_user_func_array(array($field, $methodName), $arguments);
                        }
                    }
                }

                if ($field instanceof FileAttachmentField) 
                {
                    // fix(Jake)
                    // todo(Jake): Fix deletion

                    // Support for Unclecheese's Dropzone module
                    // @see: https://github.com/unclecheese/silverstripe-dropzone/tree/1.2.3
                    $action = $this->getActionURL($field, $record);
                    $field = MultiRecordFileAttachmentField::cast($field);
                    $field->multiRecordAction = $action;

                    // Fix $field->Value()
                    if ($recordShouldSetValue && !$val && isset($record->{$fieldName.'ID'}))
                    {
                        // NOTE(Jake): This check was added for 'FileAttachmentField'.
                        //             Putting this outside of this 'instanceof' if-statement will break UploadField.
                        $val = $record->__get($fieldName.'ID');
                        if ($val)
                        {
                            $field->setValue($val, $record);
                        }
                    }
                }
                else if (class_exists('MultiRecord'.$field->class))
                {
                    // Handle generic case (ie. UploadField)
                    // Where we just want to override value returned from $field->Link()
                    // so FormField actions work.
                    $class = 'MultiRecord'.$field->class;
                    $fieldCopy = $class::create($field->getName(), $field->Title());
                    foreach (get_object_vars($field) as $property => $value)
                    {
                        $fieldCopy->$property = $value;
                    }
                    $fieldCopy->multiRecordAction = $this->getActionURL($field, $record);
                    $field = $fieldCopy;
                }
                // NOTE(Jake): Should probably add an ->extend() so other modules can monkey patch fields.
                //             Will wait to see if its needed.
            }

            // NOTE(Jake): Required to support UploadField. Generic so any field can utilize this functionality.
            if (method_exists($field, 'setRecord') || (method_exists($field, 'hasMethod') && $field->hasMethod('setRecord'))) {
                $field->setRecord($record);
            }

            $currentFieldListModifying->push($field);
        }

        $resultFieldList = new FieldList();
        $resultFieldList->push($subRecordField);
        $resultFieldList->setForm($this->form);
        return $resultFieldList;
    }

    /**
     * @param FormField $field
     * @param DataObject $record
     * @return string
     */
    public function getActionURL($field, $record)
    {
        // Example of input data
        // ---------------
        //  Level 1 Nest:
        //  -------------
        //  [0] => ElementArea [1] => MultiRecordField [2] => ElementGallery [3] => new_2 [4] => Images
        //
        //  Level 2 Nest:
        //  -------------
        //                     [5] => MultiRecordField [6] => ElementGallery_Item [7] => new_2 [8] => Items) 
        // 
        //
        $nameData = $this->getUniqueFieldName($field, $record);
        $nameData = explode('__', $nameData);
        $nameDataCount = count($nameData);
        $action = $nameData[0];
        for ($i = 1; $i < $nameDataCount; $i += 4)
        {
            $signature = $nameData[$i];
            if ($signature !== 'MultiRecordField')
            {
                throw new LogicException('Error caused by developer. Invalid signature in "MultiRecordField". Signature: '.$signature);
            }
            $class = $nameData[$i + 1];
            $id = $nameData[$i + 2];
            if ($record->MultiRecordField_NewID || strpos($id, 'o-multirecordediting') !== FALSE) {
                $id = 'new';
            }
            $subFieldName = $nameData[$i + 3];
            $action .= '/addinlinerecord/'.$class.'/'.$id.'/field/'.$subFieldName;
        }
        return $action;
    }

    /**
     * Re-write field names to be unique
     * ie. 'Title' to be 'ElementArea__MultiRecordField__ElementGallery__Title'
     *
     * @return \MultiRecordField
     */
    public function applyUniqueFieldNames($fields, $record)
    {
        $isReadonly = $this->isReadonly();
        foreach ($fields->dataFields() as $field)
        {
            // Get all fields underneath/nested in MultiRecordSubRecordField
            $name = $this->getUniqueFieldName($field, $record);
            $field->setName($name);
        }
        foreach ($fields as $field)
        {
            // This loop is at a top level, so it should all technically just be
            // MultiRecordSubRecordField's only.
            if ($field instanceof MultiRecordSubRecordField) {
                $name = $this->getUniqueFieldName($field, $record);
                $field->setName($name);
            }
            if ($isReadonly) {
                $fields->replaceField($field->getName(), $field = $field->performReadonlyTransformation());
            }
        }
        return $this;
    }

    /**
     * @param string|FormField $field
     * @param DataObject $record
     * @return string
     */
    public function getUniqueFieldName($fieldOrFieldname, $record)
    {
        $name = $fieldOrFieldname instanceof FormField ? $fieldOrFieldname->getName() : $fieldOrFieldname;
        $recordID = $this->getFieldID($record);

        return sprintf(
            '%s__%s__%s__%s__%s', $this->getName(), 'MultiRecordField', $record->ClassName, $recordID, $name
        );
    }

    private static $_new_records_to_write = null;
    private static $_existing_records_to_write = null;
    private static $_records_to_delete = null;
    public function saveInto(\DataObjectInterface $record)
    {
        if ($this->depth == 1)
        {
            // Reset records to write for top-level MultiRecordField.
            self::$_new_records_to_write = array();
            self::$_existing_records_to_write = array();
            self::$_records_to_delete = array();
        }

        $class_id_field = $this->Value();
        if (!$class_id_field)
        {
            return $this;
        }

        $list = $this->list;

        // Workaround for #5775 - Fix bug where ListboxField writes to $record, making
        //                        UnsavedRelationList redundant.
        // https://github.com/silverstripe/silverstripe-framework/pull/5775
        $relationName = $this->getName();
        $relation = ($record->hasMethod($relationName)) ? $record->$relationName() : null;
        if ($relation) {
            // When ListboxField (or other) has saved a new record in its 'saveInto' function
            if ($record->ID && $list instanceof UnsavedRelationList) {
                if ($this->config()->enable_patch_5775 === false)
                {
                    throw new Exception("ListboxField or another FormField called DataObject::write() when it wasn't meant to on your unsaved record. https://github.com/silverstripe/silverstripe-framework/pull/5775 ---- Enable 'enable_patch_5775' in your config YML against ".__CLASS__." to enable a workaround.");
                }
                if ($relation instanceof ElementalArea) {
                    // Hack to support Elemental
                    $relation = $relation->Elements();
                } else if ($relation instanceof DataObject) {
                    throw new Exception("Unable to use enable_patch_5775 workaround as \"".$record->class."\"::\"".$relationName."\"() does not return a DataList.");
                }
                $list = $relation;
            }
        }

        $flatList = array();
        if ($list instanceof DataList) 
        {
            $flatList = array();
            foreach ($list as $r) 
            {
                $flatList[$r->ID] = $r;
            }
        }
        else if (!$list instanceof UnsavedRelationList)
        {
            throw new Exception('Expected SS_List, but got "'.$list->class.'" in '.__CLASS__);
        }

        $sortFieldName = $this->getSortFieldName();

        foreach ($class_id_field as $class => $id_field)
        {
            // Create and add records to list
            foreach ($id_field as $idString => $subRecordData)
            {
                if (strpos($idString, 'o-multirecordediting') !== FALSE)
                {
                    throw new Exception('Invalid template ID passed in ("'.$idString.'"). This should have been replaced by MultiRecordField.js. Is your JavaScript broken?');
                }
                $idParts = explode('_', $idString);
                $id = 0;
                $subRecord = null;
                if ($idParts[0] === 'new')
                {
                    if (!isset($idParts[1]))
                    {
                        throw new Exception('Missing ID part of "new_" identifier.');
                    }
                    $id = (int)$idParts[1];
                    if (!$id && $id > 0)
                    {
                        throw new Exception('Invalid ID part of "new_" identifier. Positive Non-Zero Integers only are accepted.');
                    }

                    // New record
                    $subRecord = $class::create();
                }
                else
                {
                    $id = $idParts[0];
                    // Find existing
                    $id = (int)$id;
                    if (!isset($flatList[$id])) {
                        throw new Exception('Record #'.$id.' on "'.$class.'" does not exist in this DataList context. (From ID string: '.$idString.')');
                    }
                    $subRecord = $flatList[$id];
                }

                // Detect if record was deleted
                if (isset($subRecordData['multirecordfield_delete']) && $subRecordData['multirecordfield_delete'])
                {
                    if ($subRecord && $subRecord->exists()) {
                        self::$_records_to_delete[] = $subRecord;
                    }
                    continue;
                }

                // maybetodo(Jake): To improve performance, maybe add 'dumb fields' config where it just gets the fields available
                //                  on an unsaved record and just re-uses them for each instance. Of course
                //                  this means conditional fields based on parent values/db values wont work.
                $fields = $this->getRecordDataFields($subRecord);
                $fields = $fields->dataFields();
                if (!$fields) {
                    throw new Exception($class.' is returning 0 fields.');
                }

                //
                foreach ($subRecordData as $fieldName => $fieldData)
                {
                    if ($sortFieldName !== $fieldName && 
                        !isset($fields[$fieldName]))
                    {
                        // todo(Jake): Say whether its missing the field from getCMSFields or getMultiRecordFields or etc.
                        throw new Exception('Missing field "'.$fieldName.'" from "'.$subRecord->class.'" fields based on data sent from client. (Could be a hack attempt)');
                    }
                    $field = $fields[$fieldName];
                    if (!$field instanceof MultiRecordField)
                    {
                        $value = $fieldData->value;
                    }
                    else
                    {
                        $value = $fieldData;
                    }
                    // NOTE(Jake): Added for FileAttachmentField as it uses the name used in the request for 
                    //             file deletion.
                    $field->MultiRecordEditing_Name = $this->getUniqueFieldName($field->getName(), $subRecord);
                    $field->setValue($value);
                    // todo(Jake): Some field types (ie. UploadField/FileAttachmentField) directly modify the record
                    //             on 'saveInto', meaning people -could- circumvent certain permission checks
                    //             potentially. Must test this or defer extensions of 'FileField' to 'saveInto' later.
                    $field->saveInto($subRecord);
                    $field->MultiRecordField_SavedInto = true;
                }

                // Handle sort if its not manually handled on the form
                if ($sortFieldName && !isset($fields[$sortFieldName]))
                {
                    $newSortValue = $id; // Default to order added
                    if (isset($subRecordData[$sortFieldName])) {
                        $newSortValue = $subRecordData[$sortFieldName];
                    }
                    if ($newSortValue)
                    {
                        $subRecord->{$sortFieldName} = $newSortValue;
                    }
                }

                // Check if sort value is invalid
                $sortValue = $subRecord->{$sortFieldName};
                if ($sortValue <= 0)
                {
                    throw new Exception('Invalid sort value ('.$sortValue.') on #'.$subRecord->ID.' for class '.$subRecord->class.'. Sort value must be greater than 0.');
                }
                
                if (!$subRecord->doValidate())
                {
                    throw new ValidationException('Failed validation on '.$subRecord->class.'::doValidate() on record #'.$subRecord->ID);
                }

                if ($subRecord->exists()) {
                    self::$_existing_records_to_write[] = $subRecord;
                } else {
                    // NOTE(Jake): I used to directly add the record to the list here, but
                    //             if it's a HasManyList/ManyManyList, it will create the record
                    //             before doing permission checks.
                    self::$_new_records_to_write[] = array(
                        self::NEW_RECORD => $subRecord,
                        self::NEW_LIST   => $list,
                    );
                }
            }
        }

        // The top-most MutliRecordField handles all the permission checking/saving at once
        if ($this->depth == 1)
        {
            // Remove records from list that haven't been changed to avoid unnecessary 
            // permission check and ->write overhead
            foreach (self::$_existing_records_to_write as $i => $subRecord)
            {
                $hasRecordChanged = false;
                $changedFields = $subRecord->getChangedFields(true);
                foreach ($changedFields as $field => $data)
                {
                    $hasRecordChanged = $hasRecordChanged || ($data['before'] != $data['after']);
                }
                if (!$hasRecordChanged)
                {
                    // Remove from list, stops the record from calling ->write()
                    unset(self::$_existing_records_to_write[$i]);
                }
            }

            //
            // Check permissions on everything at once
            // (includes records added in nested-nested-nested-etc MultiRecordField's)
            //
            $currentMember = Member::currentUser();
            $recordsPermissionUnable = array();
            foreach (self::$_new_records_to_write as $subRecordAndList) 
            {
                $subRecord = $subRecordAndList[self::NEW_RECORD];
                // Check each new record to see if you can create them
                if (!$subRecord->canCreate($currentMember)) 
                {
                    $recordsPermissionUnable['canCreate'][$subRecord->class][$subRecord->ID] = true;
                }
            }
            foreach (self::$_existing_records_to_write as $subRecord) 
            {
                // Check each existing record to see if you can edit them
                if (!$subRecord->canEdit($currentMember))
                {
                    $recordsPermissionUnable['canEdit'][$subRecord->class][$subRecord->ID] = true;
                }
            }
            foreach (self::$_records_to_delete as $subRecord)
            {
                // Check each record deleting to see if you can delete them
                if (!$subRecord->canDelete($currentMember))
                {
                    $recordsPermissionUnable['canDelete'][$subRecord->class][$subRecord->ID] = true;
                }
            }
            if ($recordsPermissionUnable)
            {
                /**
                 * Output a nice exception/error message telling you exactly what records/classes
                 * the permissions failed on. 
                 *
                 * eg.
                 * Current member #7 does not have permission.
                 *
                 * Unable to "canCreate" records: 
                 * - ElementGallery (26)
                 *
                 * Unable to "canEdit" records: 
                 * - ElementGallery (24,23,22)
                 * - ElementGallery_Item (16,23,17,18,19,20,22,21)
                 */
                $message = '';
                foreach ($recordsPermissionUnable as $permissionFunction => $classAndID)
                {
                    $message .= "\n".'Unable to "'.$permissionFunction.'" records: '."\n";
                    foreach ($classAndID as $class => $idAsKeys)
                    {
                        $message .= '- '.$class.' ('.implode(',', array_keys($idAsKeys)).')'."\n";
                    }
                }
                throw new Exception('Current member #'.Member::currentUserID().' does not have permission.'."\n".$message);
            }

            // Add new records into the appropriate list
            foreach (self::$_new_records_to_write as $subRecordAndList) 
            {
                $list = $subRecordAndList[self::NEW_LIST];
                if ($list instanceof UnsavedRelationList 
                    || $list instanceof RelationList) // ie. HasManyList/ManyManyList
                {
                    $subRecord = $subRecordAndList[self::NEW_RECORD];
                    // NOTE(Jake): Adding an empty record into an existing ManyManyList/HasManyList -seems- to create that record.
                    $list->add($subRecord);
                }
                else 
                {
                    throw new Exception('Unsupported SS_List type "'.$list->class.'"');
                }
            }

            // Debugging (for looking at UnsavedRelationList's to ensure $_new_records_to_write is working)
            // NOTE(Jake): Added to debug Frontend Objects module support
            //Debug::dump($record); Debug::dump($relation_class_id_field); exit('Exited at: '.__CLASS__.'::'.__FUNCTION__);// Debug raw request information tree

            // Save existing items
            foreach (self::$_existing_records_to_write as $subRecord) 
            {
                // NOTE(Jake): Records are checked above to see if they've been changed.
                //             If they haven't been changed, they're removed from the 'self::$_existing_records_to_write' list.
                $subRecord->write();
            }

            // Remove deleted items
            foreach (self::$_records_to_delete as $subRecord) 
            {
                $subRecord->delete();
            }
        }
    }

    /**
     * @return FieldList
     */
    public function Fields() {
        return $this->children;
    }

    /**
     * @return FieldList
     */
    public function Actions() {
        if (!$this->getCanAddInline())
        {
            return new FieldList();
        }
        if ($this->actions)
        {
            return $this->actions;
        }

        // Setup default actions
        $this->actions = new FieldList;
        $this->actions->unshift($inlineAddButton = FormAction::create('AddInlineRecord', 'Add')
                            ->addExtraClass('multirecordfield-addinlinebutton js-multirecordfield-add-inline')
                            ->setAttribute('autocomplete', 'off')
                            ->setUseButtonTag(true));
        $this->actions->unshift($classField = DropdownField::create('ClassName', ' ')
                                            ->addExtraClass('multirecordfield-classname js-multirecordfield-classname')
                                            ->setAttribute('autocomplete', 'off')
                                            ->setEmptyString('(Select section type to create)'));
        $inlineAddButton->addExtraClass('ss-ui-action-constructive ss-ui-button');
        $inlineAddButton->setAttribute('data-icon', 'add');

        // NOTE(Jake): add 'updateActions' here if needed later.

        // Update FormAction fields with button classes
        // todo(Jake): Find a better location for applying this
        $this->applyButtonClasses($this->actions);

        return $this->actions;
    }

    /**
     * Returns a read-only version of this field.
     *
     * @return MultiRecordField_Readonly
     */
    public function performReadonlyTransformation() {
        $resultField = MultiRecordField_Readonly::create($this->name, $this->title, $this->list);
        foreach (get_object_vars($this) as $property => $value)
        {
            $resultField->$property = $value;
        }
        $resultField->readonly = true;
        return $resultField;
    }

    /**
     * @return FieldList
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     * @return string
     */
    public function getSortFieldName() {
        if ($this->sortFieldName || ($this->sortFieldName === '' || $this->sortFieldName === false)) {
            return $this->sortFieldName;
        }

        $modelClasses = $this->getModelClassesOrThrowExceptionIfEmpty();
        $class = key($modelClasses);
        $baseClass = ClassInfo::baseDataClass($class);
        $sort = Config::inst()->get($baseClass, 'default_sort');
        if (!$sort) {
            return null;
        }

        $sort = static::sort_string_to_array($sort);
        $sort = key($sort);
        $sort = str_replace(array('"', '\'', '`'), '', $sort); // Strip quotes as core and some modules use them for 'default_sort'
        if (strpos($sort, '.') !== FALSE) {
            throw new Exception('Cannot use relational sort field with '.__CLASS__.', default_sort config for "'.$baseClass.'" is incompatible.');
        }

        return $sort;
    }

    /**
     * Set what field to use for sorting the records. Setting to false or blank string will explictly disable the sort.
     *
     * @param string $name
     * @return MultiRecordField
     */
    public function setSortFieldName($name) {
        $this->sortFieldName = $name;
        return $this;
    }

    /**
     * Parse sort string into an array of sorts
     *
     * @return array
     */
    protected static function sort_string_to_array($clauses) {
        //
        // NOTE(Jake): The below code is essentially copy-pasted from SQLSelect::addOrderBy.
        //
        if(is_string($clauses)) {
            if(strpos($clauses, '(') !== false) {
                $sort = preg_split("/,(?![^()]*+\\))/", $clauses);
            } else {
                $sort = explode(',', $clauses);
            }

            $clauses = array();

            $direction = null;
            foreach($sort as $clause) {
                list($column, $direction) = static::get_direction_from_string($clause, $direction);
                $clauses[$column] = $direction;
            }
        }

        $orderby = array();
        if(is_array($clauses)) {
            foreach($clauses as $key => $value) {
                if(!is_numeric($key)) {
                    $column = trim($key);
                    $columnDir = strtoupper(trim($value));
                } else {
                    list($column, $columnDir) = static::get_direction_from_string($value);
                }

                $orderby[$column] = $columnDir;
            }
        } else {
            user_error(__CLASS__.'::'.__FUNCTION__.'() incorrect format for default_sort', E_USER_WARNING);
        }

        return $orderby;
    }

    /**
     * @return array
     */
    protected static function get_direction_from_string($value, $defaultDirection = null) {
        //
        // NOTE(Jake): The below code is essentially copy-pasted from SQLSelect::getDirectionFromString.
        //
        if(preg_match('/^(.*)(asc|desc)$/i', $value, $matches)) {
            $column = trim($matches[1]);
            $direction = strtoupper($matches[2]);
        } else {
            $column = $value;
            $direction = $defaultDirection ? $defaultDirection : "ASC";
        }
        return array($column, $direction);
    }

    /**
     * Prepares everything just before rendering the field
     */
    protected function prepareForRender() {
        if (!$this->preparedForRender) 
        {
            $this->preparedForRender = true;
            if (!$this->isReadonly() && $this->depth == 1)
            {
                // NOTE(Jake): jQuery.ondemand is required to allow FormField classes to add their own
                //             Requirements::javascript on-the-fly.
                //Requirements::javascript(FRAMEWORK_DIR . "/thirdparty/jquery/jquery.js");
                Requirements::css(MULTIRECORDEDITOR_DIR.'/css/MultiRecordField.css');
                if (is_subclass_of(Controller::curr(), 'LeftAndMain')) {
                    // NOTE(Jake): Only include in CMS to fix margin issues. Not in the main CSS file
                    //             so that the frontend CSS is less in the way.
                    Requirements::css(MULTIRECORDEDITOR_DIR.'/css/MultiRecordFieldCMS.css');
                }

                Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui.css');
                Requirements::javascript(FRAMEWORK_DIR . '/thirdparty/jquery-ui/jquery-ui.js');
                Requirements::javascript(FRAMEWORK_DIR . '/javascript/jquery-ondemand/jquery.ondemand.js');
                Requirements::javascript(THIRDPARTY_DIR . '/jquery-entwine/dist/jquery.entwine-dist.js');
                Requirements::javascript(MULTIRECORDEDITOR_DIR.'/javascript/MultiRecordField.js');

                // If config is set to 'default' but 'default' isn't configured, fallback to 'cms'.
                // NOTE(Jake): In SS 3.2, 'default' is the default active config but its not configured.
                $availableConfigs = HtmlEditorConfig::get_available_configs_map();
                $activeIdentifier = HtmlEditorConfig::get_active_identifier();
                if ($activeIdentifier === 'default' && !isset($availableConfigs[$activeIdentifier]))
                {
                    HtmlEditorConfig::set_active('cms');
                }
            }

            //
            // Setup actions
            //
            $actions = $this->Actions();
            if ($actions && $actions->count())
            {
                $modelClasses = $this->getModelClassesOrThrowExceptionIfEmpty();
                $modelFirstClass = key($modelClasses);

                $inlineAddButton = $actions->dataFieldByName('action_AddInlineRecord');
                if ($inlineAddButton)
                {
                    // Setup default inline field data attributes
                    //$inlineAddButton->setAttribute('data-name', $this->getName());
                    $inlineAddButton->setAttribute('data-action', $this->getName());
                    $inlineAddButton->setAttribute('data-class', $modelFirstClass);
                    $inlineAddButton->setAttribute('data-depth', $this->depth);
                    // Automatically apply all data attributes on this element, to the inline button.
                    foreach ($this->getAttributes() as $name => $value)
                    {
                        if (substr($name, 0, 5) === 'data-')
                        {
                            $inlineAddButton->setAttribute($name, $value);
                        }
                    }
                    if (count($modelClasses) == 1) 
                    {
                        $name = singleton($modelFirstClass)->i18n_singular_name();
                        $inlineAddButton->setTitle('Add '.$name);
                    }
                }

                $classField = $actions->dataFieldByName('ClassName');
                if ($classField)
                {
                    if (count($modelClasses) > 1) 
                    {
                        if ($inlineAddButton)
                        {
                            $inlineAddButton->setDisabled(true);
                        }
                        $classField->setSource($modelClasses);
                    }
                    else
                    {
                        $actions->removeByName('ClassName');
                    }
                }

                // Allow outside sources to influences the disable state class-wise
                if ($inlineAddButton && $inlineAddButton->isDisabled())
                {
                    $inlineAddButton->addExtraClass('is-disabled');
                }

                //
                foreach ($actions as $actionField)
                {
                    // Expand out names
                    $actionField->setName($this->getName().'_'.$actionField->getName());
                }
            }

            // Get existing records to add fields for
            $recordArray = array();
            if ($this->list && !$this->list instanceof UnsavedRelationList) 
            {
                foreach ($this->list->toArray() as $record)
                {
                    $recordArray[$record->ID] = $record;
                }
            }

            //
            // If the user validation failed, Value() will be populated with some records
            // that have 'new_' IDs, so handle them.
            //
            $value = $this->Value();
            if ($value && is_array($value))
            {
                foreach ($value as $class => $recordDatas) 
                {
                    foreach ($recordDatas as $new_id => $fieldData) 
                    {
                        if (substr($new_id, 0, 4) === 'new_')
                        {
                            $record = $class::create();
                            $record->MultiRecordField_NewID = $new_id;
                            $recordArray[$new_id] = $record;
                        }
                        else if ($new_id == (string)(int)$new_id)
                        {
                            // NOTE(Jake): "o-multirecordediting-1-id" == 0 // evaluates true in PHP 5.5.12, 
                            //             So we need to make it a string again to avoid that dumb case.
                            $new_id = (int)$new_id;
                            if (!isset($recordArray[$new_id]))
                            {
                                throw new Exception('Record #'.$new_id.' does not exist in this context.');
                            }
                            $record = $recordArray[$new_id];
                            //throw new Exception('todo, handle existing stuff that fails validation. ('.$new_id.')');
                        }
                        else
                        {
                            throw new Exception('Validation failed and unable to restore fields with invalid ID. ('.$new_id.')');
                        }

                        // Update new/existing record with data
                        foreach ($fieldData as $fieldName => $fieldInfo) 
                        {
                            if (is_array($fieldInfo)) {
                                $record->$fieldName = $fieldInfo;
                            } else {
                                $record->$fieldName = $fieldInfo->value;
                            }
                        }
                    }
                }
            }

            // Transform into list
            $recordList = new ArrayList($recordArray);

            // Ensure all the records are sorted by the sort field
            $sortFieldName = $this->getSortFieldName();
            if ($sortFieldName)
            {
                $recordList = $recordList->sort($sortFieldName);
            }

            //
            // Return all fields from the records editing
            //
            foreach ($recordList as $record)
            {
                $recordFields = $this->getRecordDataFields($record);
                $this->applyUniqueFieldNames($recordFields, $record);
                foreach ($recordFields as $field)
                {
                    $this->children->push($field);
                }
            }
        }
    }

    public function FieldHolder($properties = array()) {
        $this->prepareForRender();
        $this->addExtraClass($this->Type().'_holder');
        return parent::FieldHolder($properties);
    }

    public function Field($properties = array()) {
        $this->prepareForRender();
        $this->removeExtraClass($this->Type().'_holder');
        $this->addExtraClass($this->Type().'_field');
        return parent::Field($properties);
    }
}

class MultiRecordField_Readonly extends MultiRecordField {
    protected $readonly = true;

    public function handleRequest(SS_HTTPRequest $request, DataModel $model) {
        return null;
    }

    /**
     * @return FieldList
     */
    public function Actions() {
        return new FieldList();
    }
}