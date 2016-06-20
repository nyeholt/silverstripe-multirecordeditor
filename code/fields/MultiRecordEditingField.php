<?php

/**
 * @author Jake Bentvelzen, Marcus
 */
class MultiRecordEditingField extends FormField {
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
     * For tracking field (sent/expanded names) and values in
     * 'saveInto'
     */
    const FIELD_NAME = 0;
    const FIELD_VALUE = 1;

    /**
     * Set variables or call functions on certain fields underneath this field.
     * ie. Change rows to 6 for HtmlEditorField so it takes less space.
     *
     * @config
     * @var array
     */
    private static $default_config = array(
        'HtmlEditorField' => array(
            'functions' => array(
                'setRows' => 6,
            ),
        ),
    );

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
     * @var array List of additional CSS classes for the form tag.
     */
    protected $extraClasses = array();

    /**
     * How nested inside other MultiRecordEditingField's this field is.
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
            if (!$record->canCreate())
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
            if (!$record->canEdit())
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

        // Re-write field names to be unique
        // ie. 'Title' to be 'ElementArea__MultiRecordEditingField__ElementGallery__Title'
        foreach ($dataFields as $field)
        {
            $name = $this->getFieldName($field, $record);
            $field->setName($name);
        }

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

        return $this->renderWith(array('MultiRecordEditingField_addinline'));
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
     * @return \MultiRecordEditingField
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
     * @return \MultiRecordEditingField
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
     * @return \MultiRecordEditingField
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @return \MultiRecordEditingField
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
     * If array, can be formatted like so:
     * array('MyClass', 'MyOtherClass')
     * -or-
     * array('MyClass' => 'Nice Name 1', 'MyOtherClass' => 'Nice Name 2')
     *
     * The classes provided must all extend the same parent.
     *
     * @param array|string $modelClassName
     *
     * @return $this
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
        // NOTE(Jake): Not '{%=o.multirecordediting.id%}' with tmpl.js because SS strips '{', '}' and replaces '.' with '-'
        return 'o-multirecordediting-'.$this->depth.'-id';
    }

    /**
     * @return ArrayList
     */
    /*public function getRecords()
    {
        Deprecation::notice('2.0', __FUNCTION__.' is deprecated. Use getList instead. Change made to keep this field more consistent with GridField API.');
        return $this->list;
    }*/

    /**
     * @return SS_List
     */
    public function getList() {
        return $this->list;
    }

    /**
     * @param SS_List $list
     * @return \MultiRecordEditingField
     */
    public function setList(SS_List $list) {
        $this->list = $list;
        return $this;
    }

    /**
     * @param Form $form
     * @return \MultiRecordEditingField
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
     * If string, then set the method to call on the record to get fields.
     * If closure, then call the method for the fields with $record as the first parameter.
     *
     * @return MultiRecordEditingField
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
                $sortValue = ($record && $record->exists()) ? $record->$sortFieldName : self::SORT_INVALID;
                $sortField = HiddenField::create($sortFieldName);
                if ($sortField instanceof HiddenField) {
                    $sortField->setAttribute('value', $sortValue);
                } else {
                    $sortField->setValue($sortValue);
                }
                // NOTE(Jake): Uses array_merge() to prepend the sort field in the $fields associative array.
                //             The sort field is prepended so jQuery.find('.js-multirecordediting-sort-field').first()
                //             finds the related sort field to this, rather than a sort field nested deeply in other
                //             MultiRecordEditingField's.
                $fields = array_merge(array(
                    $sortFieldName => $sortField
                ), $fields);
            }
            $sortField->addExtraClass('js-multirecordediting-sort-field');
        }

        // Set heading (ie. 'My Record (Draft)')
        $recordExists = $record->exists();
        $recordSectionTitle = $record->Title;
        $status = ($recordExists) ? $record->CMSPublishedState : 'New';
        if ($status) {
            $recordSectionTitle .= ' ('.$status.')';
        }
        if (!$recordSectionTitle) {
            // NOTE(Jake): Ensures no title'd ToggleCompositeField's have a proper height.
            $recordSectionTitle = '&nbsp;';
        }

        // Add heading field / Togglable composite field with heading
        $recordID = $this->getFieldID($record);
        $subRecordField = MultiRecordEditingSubRecordField::create('MultiRecordEditingSubRecordField'.$recordID, $recordSectionTitle, null);
        $subRecordField->setParent($this);
        $subRecordField->setRecord($record);

        // Modify sub-fields to work properly with this field
        $currentFieldListModifying = $subRecordField;
        foreach ($fields as $field)
        {
            $fieldName = $field->getName();

            // Set value from record
            if ($recordExists)
            {
                $val = null;
                if (isset($record->$fieldName) 
                    || $record->hasMethod($fieldName)
                    || ($record->hasMethod('hasField') && $record->hasField($fieldName)))
                {
                    $val = $record->__get($fieldName);
                }
                // NOTE(Jake): Some fields like 'CheckboxSetField' require the DataObject/record as the 2nd parameter
                $field->setValue($val, $record);
            }

            if ($field instanceof MultiRecordEditingField) {
                $field->depth = $this->depth + 1;
                $action = $this->getActionName($field, $record);
                $field->setAttribute('data-action', $action);
                // NOTE(Jake): Unclear at time of writing (17-06-2016) if nested MultiRecordEditingField should
                //             inherit certain settings or not.
                $field->setFieldsFunction($this->getFieldsFunction(), $this->fieldsFunctionFallback);
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

                if ($field instanceof UploadField) 
                {
                    // Rewrite UploadField's "Select file" iframe to go through
                    // this field.
                    $action = $this->getActionName($field, $record);

                    $field = MultiRecordEditingUploadField::cast($field);
                    $field->multiRecordEditingFieldAction = $action;
                }
                else if ($field instanceof FileAttachmentField) 
                {
                    // fix(Jake)
                    // todo(Jake): Fix deletion

                    // Support for Unclecheese's Dropzone module
                    // @see: https://github.com/unclecheese/silverstripe-dropzone/tree/1.2.3
                    $action = $this->getActionName($field, $record);
                    $field = MultiRecordEditingFileAttachmentField::cast($field);
                    $field->multiRecordEditingFieldAction = $action;

                    // Fix $field->Value()
                    if ($recordExists && !$val && isset($record->{$fieldName.'ID'}))
                    {
                        // NOTE(Jake): This check was added for 'FileAttachmentField'.
                        //             Putting this outside of this if-statement will break UploadField.
                        $val = $record->__get($fieldName.'ID');
                        if ($val)
                        {
                            $field->setValue($val, $record);
                        }
                    }
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
    public function getActionName($field, $record)
    {
        // Example of data
        // ---------------
        //  Level 1 Nest:
        //  -------------
        //  [0] => ElementArea [1] => MultiRecordEditingField [2] => ElementGallery [3] => new_2 [4] => Images
        //
        //  Level 2 Nest:
        //  -------------
        //                     [5] => MultiRecordEditingField [6] => ElementGallery_Item [7] => new_2 [8] => Items) 
        // 
        //
        $nameData = $this->getFieldName($field, $record);
        $nameData = explode('__', $nameData);
        $nameDataCount = count($nameData);
        $action = $nameData[0];
        for ($i = 1; $i < $nameDataCount; $i += 4)
        {
            $signature = $nameData[$i];
            if ($signature !== 'MultiRecordEditingField')
            {
                throw new LogicException('Error caused by developer. Invalid signature in "MultiRecordEditingField". Signature: '.$signature);
            }
            $class = $nameData[$i + 1];
            $id = $nameData[$i + 2];
            if (strpos($id, 'o-multirecordediting') !== FALSE) {
                $id = 'new';
            }
            $subFieldName = $nameData[$i + 3];
            $action .= '/addinlinerecord/'.$class.'/'.$id.'/field/'.$subFieldName;
        }
        return $action;
    }

    /**
     * @param string|FormField $field
     * @param DataObject $record
     * @return string
     */
    protected function getFieldName($fieldOrFieldname, $record)
    {
        $name = $fieldOrFieldname instanceof FormField ? $fieldOrFieldname->getName() : $fieldOrFieldname;
        $recordID = $this->getFieldID($record);

        return sprintf(
            '%s__%s__%s__%s__%s', $this->getName(), 'MultiRecordEditingField', $record->ClassName, $recordID, $name
        );
    }

    private static $_new_records_to_write = null;
    private static $_existing_records_to_write = null;
    public function saveInto(\DataObjectInterface $record)
    {
        $class_id_field = $this->Value();
        if (is_array($class_id_field))
        {
            if (!$class_id_field)
            {
                throw new Exception('No data passed into '.__CLASS__.'::'.__FUNCTION__.'.');
            }

            $list = $this->list;
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
                throw new Exception('List class "'.$list->class.'" not supported by '.__CLASS__);
            }

            $sortFieldName = $this->getSortFieldName();

            foreach ($class_id_field as $class => $id_field)
            {
                // Create and add records to list
                foreach ($id_field as $idString => $subRecordData)
                {
                    if (strpos($idString, 'o-multirecordediting') !== FALSE)
                    {
                        throw new Exception('Invalid template ID passed in ("'.$idString.'"). This should have been replaced by MultiRecordEditingField.js. Is your JavaScript broken?');
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

                    // maybetodo(Jake): if performance is sluggish, make any new records share
                    //             the same fields as they should all output the same.
                    //             (ie. record->ID == 0 to cache fields)
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
                            // todo(Jake): Say whether its missing the field from getCMSFields or getMultiRecordEditingFields or etc.
                            throw new Exception('Missing field "'.$fieldName.'" from "'.$subRecord->class.'" fields based on data sent from client. (Could be a hack attempt)');
                        }
                        $field = $fields[$fieldName];
                        if (!$field instanceof MultiRecordEditingField)
                        {
                            $value = $fieldData[self::FIELD_VALUE];
                        }
                        else
                        {
                            $value = $fieldData;
                        }
                        // NOTE(Jake): Added for FileAttachmentField as it uses the name used in the request for 
                        //             file deletion.
                        $field->MultiRecordEditing_Name = $this->getFieldName($field->getName(), $subRecord);
                        $field->setValue($value);
                        // todo(Jake): Some field types (ie. UploadField/FileAttachmentField) directly modify the record
                        //             on 'saveInto', meaning people -could- circumvent certain permission checks
                        //             potentially. Must test this or defer extensions of 'FileField' to 'saveInto' later.
                        $field->saveInto($subRecord);
                        $field->MultiRecordEditingField_SavedInto = true;
                    }

                    // NOTE(Jake): FileAttachmentField uses a hack for deleting records, meaning sometimes
                    //             saveInto() won't be called due to the structure of the SS_HTTPRequest postVar name.
                    foreach ($fields as $fieldName => $field)
                    {
                        if ($field instanceof FileAttachmentField && !$field->MultiRecordEditingField_SavedInto)
                        {
                            $field->MultiRecordEditing_Name = $this->getFieldName($field->getName(), $subRecord);
                            $field->saveInto($subRecord);
                            $field->MultiRecordEditingField_SavedInto = true;
                        }
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
                        throw new ValidationException('Invalid sort value ('.$sortValue.') on #'.$subRecord->ID.' for class '.$subRecord->class.'. Sort value must be greater than 0.');
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
            return;
        }

        // Create and save new has_many/many_many records
        /**
         * @var SS_HTTPRequest
         */
        $request = $this->form->getRequest();
        if ($request)
        {
            $relation_class_id_field = array();
            $relationFieldName = $this->getName();

            foreach ($request->requestVars() as $name => $value)
            {
                // If fieldName is the -very- first part of the string
                // NOTE(Jake): Check is required here as we're pulling straight from $request
                if (strpos($name, $relationFieldName) === 0)
                {
                    static $FIELD_PARAMETERS_SIZE = 5;

                    $fieldParameters = explode('__', $name);
                    $fieldParametersCount = count($fieldParameters);
                    if ($fieldParametersCount < $FIELD_PARAMETERS_SIZE) {
                        // You expect a name like 'ElementArea__MultiRecordEditingField__ElementGallery__new_1__Title'
                        // So ensure 5 parameters exist in the name, otherwise continue.
                        continue;
                    }
                    $signature = $fieldParameters[1];
                    if ($signature !== 'MultiRecordEditingField')
                    {
                        return $this->httpError(400, 'Invalid signature in "MultiRecordEditingField". Malformed MultiRecordEditingField sub-field or hack attempt.');
                    }

                    $parentFieldName = $fieldParameters[0];
                    $class = $fieldParameters[2];
                    $new_id = $fieldParameters[3];
                    $fieldName = $fieldParameters[4];

                    //
                    if ($fieldParametersCount == $FIELD_PARAMETERS_SIZE)
                    {
                        // 1st Nest Level
                        $relation_class_id_field[$parentFieldName][$class][$new_id][$fieldName] = array(
                            self::FIELD_NAME => $name,
                            self::FIELD_VALUE => $value
                        ); 
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
                        $relationArray[$fieldName] = array(
                            self::FIELD_NAME => $name,
                            self::FIELD_VALUE => $value
                        ); 
                        unset($relationArray);
                    }
                }
            }

            // Debugging
            //Debug::dump($relation_class_id_field); exit('Exited at: '.__CLASS__.'::'.__FUNCTION__);// Debug raw request information tree

            // Save all fields, including nested MultiRecordEditingField's
            self::$_new_records_to_write = array();
            self::$_existing_records_to_write = array();
            $this->MultiRecordEditing_Name = $this->getName();

            foreach ($relation_class_id_field as $relation => $class_id_field)
            {
                $this->setValue($class_id_field);
                $this->saveInto($record);
                $this->setValue(null);
            }

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
            // (includes records added in nested-nested-nested-etc MultiRecordEditingField's)
            //
            $recordsPermissionUnable = array();
            foreach (self::$_new_records_to_write as $subRecordAndList) 
            {
                $subRecord = $subRecordAndList[self::NEW_RECORD];
                // Check each new record to see if you can create them
                if (!$subRecord->canCreate()) 
                {
                    $recordsPermissionUnable['canCreate'][$subRecord->class][$subRecord->ID] = true;
                }
            }
            foreach (self::$_existing_records_to_write as $subRecord) 
            {
                // Check each existing record to see if you can edit them
                if (!$subRecord->canEdit())
                {
                    $recordsPermissionUnable['canEdit'][$subRecord->class][$subRecord->ID] = true;
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

            // Save existing items
            foreach (self::$_existing_records_to_write as $subRecord) 
            {
                // NOTE(Jake): Records are checked above to see if they've been changed.
                //             If they haven't been changed, they're removed from the 'self::$_existing_records_to_write' list.
                $subRecord->write();
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
        $fields = FieldList::create();
        if (!$this->getCanAddInline())
        {
            return $fields;
        }

        $modelClasses = $this->getModelClassesOrThrowExceptionIfEmpty();
        $modelFirstClass = key($modelClasses);
        $fields->unshift($inlineAddButton = FormAction::create($this->getName().'_addinlinerecord', 'Add')
                            ->addExtraClass('js-multirecordediting-add-inline')
                            ->setUseButtonTag(true));

        // Setup default inline field data attributes
        $inlineAddButton->setAttribute('data-name', $this->getName());
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
        if (count($modelClasses) > 1) 
        {
            $fields->unshift($classField = DropdownField::create($this->getName().'_ClassName', ' ')
                                            ->addExtraClass('js-multirecordediting-classname')
                                            ->setEmptyString('(Select section type to create)'));
            $classField->setSource($modelClasses);
        }
        return $fields;
    }

    /**
     * Returns a read-only version of this field.
     *
     * @return MultiRecordEditingField_Readonly
     */
    public function performReadonlyTransformation() {
        $resultField = MultiRecordEditingField_Readonly::create($this->name, $this->title, $this->list);
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
        if ($this->sortFieldName) {
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
     * @return MultiRecordEditingField
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
            $readonly = $this->isReadonly();
            if (!$readonly && $this->depth == 1)
            {
                // NOTE(Jake): jQuery.ondemand is required to allow FormField classes to add their own
                //             Requirements::javascript on-the-fly.
                //Requirements::javascript(FRAMEWORK_DIR . "/thirdparty/jquery/jquery.js");
                Requirements::css(MULTIRECORDEDITOR_DIR.'/css/MultiRecordEditingField.css');
                Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui.css');
                Requirements::javascript(FRAMEWORK_DIR . '/thirdparty/jquery-ui/jquery-ui.js');
                Requirements::javascript(FRAMEWORK_DIR . '/javascript/jquery-ondemand/jquery.ondemand.js');
                Requirements::javascript(THIRDPARTY_DIR . '/jquery-entwine/dist/jquery.entwine-dist.js');
                Requirements::javascript(MULTIRECORDEDITOR_DIR.'/javascript/MultiRecordEditingField.js');

                // If config is set to 'default' but 'default' isn't configured, fallback to 'cms'.
                // NOTE(Jake): In SS 3.2, 'default' is the default active config but its not configured.
                $availableConfigs = HtmlEditorConfig::get_available_configs_map();
                $activeIdentifier = HtmlEditorConfig::get_active_identifier();
                if ($activeIdentifier === 'default' && !isset($availableConfigs[$activeIdentifier]))
                {
                    HtmlEditorConfig::set_active('cms');
                }
            }


            foreach ($this->list as $record)
            {
                $recordFields = $this->getRecordDataFields($record);
                // Re-write field names to be unique
                // ie. 'Title' to be 'ElementArea__MultiRecordEditingField__ElementGallery__Title'
                foreach ($recordFields->dataFields() as $field)
                {
                    $name = $this->getFieldName($field, $record);
                    $field->setName($name);
                }
                foreach ($recordFields as $field)
                {
                    if ($field instanceof MultiRecordEditingSubRecordField) {
                        $field->setName($this->getFieldName($field, $record));
                    }
                    if ($readonly) {
                        $field = $field->performReadonlyTransformation();
                    }
                    $this->children->push($field);
                }
            }
        }
    }

    public function FieldHolder($properties = array()) {
        $this->prepareForRender();
        return parent::FieldHolder($properties);
    }
}

class MultiRecordEditingField_Readonly extends MultiRecordEditingField {
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