<?php

/**
 * @author marcus
 */
class MultiRecordEditingField extends FormField
{
    /**
     * Current list of records to be editable.
     *
     * @var ArrayList
     */
    protected $list;

    /**
     * The original list object passed into the object.
     * 
     * @var SS_List
     */
    protected $originalList;

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
    protected $canAddInline = true; // todo(Jake): default to off for backwards compat

    /**
     * @var FieldList
     */
    protected $children, $tabs;

    /**
     * @var array List of additional CSS classes for the form tag.
     */
    protected $extraClasses = array();

    /**
     * The MultiRecordEditingField this field belongs to. (If any)
     *
     * @var null|MultiRecordEditingField
     */
    //protected $multiRecordEditingParent;

    /**
     * @config
     * @var array
     */
    /*private static $allowed_actions = array(
        //'handleAddInline',
        'httpSubmission'
    );*/

    /**
     * @var array
     */

    /*private static $url_handlers = array(
        // ie. addinlinerecord/$ClassName/$SubFieldName/$SubAction/$SubSubFieldName/$SubSubAction
        //'addinlinerecord/$ClassName' => 'handleAddInline',
        'POST ' => 'httpSubmission',
        'GET ' => 'httpSubmission',
        'HEAD ' => 'httpSubmission',
        //'addinlinerecord/' => 'handleAddInline',
        //'addinlinerecord/$ClassName' => 'handleAddInline',
        //'addinlinerecord/$ClassName/$SubFieldName/$SubAction' => 'handleFieldAction',
    );*/

    public function __construct($name, $title = null, $recordList = null)
    {
        parent::__construct($name, $title);

        $this->children = FieldList::create();

        $this->tabs = FieldList::create();
        $this->originalList = $recordList;
        $this->list = ArrayList::create();

        /*if ($recordList) 
        {
            foreach ($recordList as $record) 
            {
                $this->addRecord($record);
            }
        }*/
    }

    /**
     * 
     */
    public function handleAddInline(SS_HTTPRequest $request) {
        // Force reset
        $this->children = FieldList::create();
        $this->tabs = FieldList::create();
        $this->list = ArrayList::create();

        // Get passed arguments
        $dirParts = explode('/', $request->remaining());
        $class = isset($dirParts[0]) ? $dirParts[0] : '';
        //$class =  $request->param('ClassName'); // todo(Jake): remove this if not neeeded
        if (!$class)
        {
            return $this->httpError(400, 'No ClassName was supplied.');
        }

        $modelClassNames = $this->getModelClasses();
        if (!isset($modelClassNames[$class]))
        {
            return $this->httpError(400, 'Invalid ClassName "'.$class.'" was supplied.');
        }

        $record = $class::create();
        if (!$record->canCreate())
        {
            return $this->httpError(400, 'Invalid permissions. Current user (#'.Member::currentUserID().') cannot create "'.$class.'" class type.');
        }

        //
        $fields = $this->getRecordDataFields($record);
        $dataFields = $fields->dataFields();

        // Determine sub field action (if executing one)
        $isSubFieldAction = (isset($dirParts[1]) && $dirParts[1] === 'field') ? true : false;
        $subField = null;
        if ($isSubFieldAction) {
            $subFieldName = (isset($dirParts[2]) && $dirParts[2]) ? $dirParts[2] : '';
            if (!isset($dataFields[$subFieldName]))
            {
                return $this->httpError(400, 'Invalid Sub-Field was supplied ('.$class.'::'.$subFieldName.').');
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
        if ($isSubFieldAction && $subField)
        {
            // Consume so Silverstripe handles the actions naturally.
            $request->shift(); // ClassName
            $request->shift(); // field
            $request->shift(); // SubFieldName
            return $subField;
        }

        // Allow fields to render, 
        $this->tabs = $fields;

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
            return $result;
        }

        $result = parent::handleRequest($request, $model);
        return $result;
    }

    /*public function handleFieldAction(SS_HTTPRequest $request) {
        // todo(Jake): refactor out into shared func
        $class = $request->param('ClassName');
        if (!$class)
        {
            return $this->httpError(400, 'No ClassName was supplied.');
        }
        $modelClassNames = $this->getModelClasses();
        if (!isset($modelClassNames[$class]))
        {
            return $this->httpError(400, 'Invalid ClassName was supplied.');
        }

        $record = $class::create();
        if (!$record->canCreate())
        {
            return $this->httpError(400, 'Invalid permissions. Current user (#'.Member::currentUserID().') cannot create "'.$class.'" class type.');
        }

        $SubFieldName = $request->param('SubFieldName');
        $SubAction = $request->param('SubAction');
        if (!$SubFieldName)
        {
            // todo(Jake): better error message
            return $this->httpError(400, 'SubField not provide.');
        }

        $SubFieldName = $this->getFieldName($SubFieldName, $record);
        $this->addRecord($record);

        $fields = $this->Fields();
        $fields = $fields->dataFields();
        if (!isset($fields[$SubFieldName]))
        {
            // todo(Jake): better error message
            return $this->httpError(400, 'SubField no exist.');
        }
        $subField = $fields[$SubFieldName];
        $request->setUrl($SubAction);
        return $subField;
    }*/

    // todo(Jake): Remove assuming being NOT composite works.
    /*public function isComposite() {
        return true; // parent::isComposite();
    }
	
	public function replaceField($fieldName, $newField) {
		// noop for a mr editing field... for now
	}

    public function collateDataFields(&$list, $saveableOnly = false) {
        foreach ($this->children as $field) {
            if ($field->isComposite()) {
                $field->collateDataFields($list, $saveableOnly);
            }

            $isIncluded = $field->hasData() && !$saveableOnly;
            if ($isIncluded) {
                $list[$field->getName()] = $field;
            }
        }
    }

    public function removeByName($fieldName, $dataFieldOnly = false) {
        return $this->children->removeByName($fieldName, $dataFieldOnly);
    }*/

    /**
     * Set a height for html editor fields
     *
     * @param int $value
     * @return \MultiRecordEditingField
     */
    public function setHtmlEditorHeight($value)
    {
        $this->htmlEditorHeight = $value;
        return $this;
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

        // Fallback to the DataList/UnsavedRelationList
        if ($this->originalList && $this->originalList instanceof UnsavedRelationList) {
            return static::convert_to_associative(array($this->originalList->dataClass()));
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
    public static function get_field_id($record) {
        if ($record && $record->ID) {
            return (int)$record->ID;
        }
        // NOTE(Jake): Not '{%=o.multirecordediting.id%}' with tmpl.js because SS strips '{', '}' and replaces '.' with '-'
        return 'o-multirecordediting-id';
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
     * @return null
     */
    /*public function editList($list)
    {
        if ($list) {
            foreach ($list as $record) {
                if ($record instanceof DataObjectInterface) {
                    $this->addRecord($record);
                }
            }
        }
    }*/

    /**
     * @return boolean
     */
    public function getCanSort() {
        return (bool)$this->getSortFieldName();
    }

    /**
     * @return FieldList|null
     */
    public function getRecordDataFields(DataObjectInterface $record) {
        if (method_exists($record, 'getMultiEditFields')) {
            // todo(Jake): Allow 'getMultiEditFields' by extension using 'hasMethod'
            $fields = $record->getMultiEditFields();
        } else {
            $fields = $record->getCMSFields();
        }
        $record->extend('updateMultiEditFields', $fields);
        $fields = $fields->dataFields();
        if (!$fields) {
            throw new Exception('todo(Jake): handle this');
            return $fields;
        }

        // Setup sort field
        $sortFieldName = $this->getSortFieldName();
        if ($sortFieldName)
        {
            // todo(Jake): allow no sort field to work + better error message
            //throw new Exception('Unable to determine sort field name.');
            $sortField = isset($fields[$sortFieldName]) ? $fields[$sortFieldName] : null;
            if ($sortField && !$sortField instanceof HiddenField)
            {
                throw new Exception('Cannot utilize drag and drop sort functionality if the sort field is explicitly used on form.');
            }
            if (!$sortField)
            {
                $sortValue = ($record && $record->exists()) ? $record->$sortField : 'o-multirecordediting-sort';
                $sortField = HiddenField::create($sortFieldName)->setAttribute('value', $sortValue);
                $fields[$sortFieldName] = $sortField;
            }
            $sortField->addExtraClass('js-multirecordediting-sort-field');
        }

        // Set heading (ie. 'My Record (Draft)')
        $recordSectionTitle = $record->Title;
        $status = ($record->ID) ? $record->CMSPublishedState : 'New';
        if ($status) {
            $recordSectionTitle .= ' ('.$status.')';
        }

        // Add heading field / Togglable composite field with heading
        $recordID = static::get_field_id($record);
        $tab = ToggleCompositeField::create('CompositeHeader'.$recordID, $recordSectionTitle, null);
        $tab->setTemplate('MultiRecordEditingField_'.$tab->class);
        $tab->setStartClosed(false);
        $tab->CanSort = $this->CanSort;
        
        /*$parentFields = null;
        if ($parentFields) {
            $parentFields->push($tab);
        } else {
            $tab->setStartClosed(false);
            $this->tabs->push($tab);
        }
        if ($parentFields) {
            // if we're not using toggles, we only add the header _if_ we're an inner item, ie $parentFields != null
            $this->children->push(HeaderField::create('RecordHeader'.$recordID, $recordSectionTitle));
        }*/

        $recordExists = $record->exists();

        $currentFieldListModifying = $tab;
        foreach ($fields as $k => $field)
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
                    $subFieldName = $nameData[$i + 3];
                    $action .= '/addinlinerecord/'.$class.'/field/'.$subFieldName;
                }
                $field->setAttribute('data-action', $action);
                /*if (count($nameData) > 5) {
                    Debug::dump(count($nameData));
                    Debug::dump($nameData); exit;
                } else {
                   // Debug::dump(count($nameData));
                   // Debug::dump($nameData); exit;
                }*/
                // Debug::dump($action); exit;

                // TODO(JAKE): REMOVE, VERY OUTDATED
                // getAction
                //$action = $this->getName().'/addinlinerecord/'.'ElementGallery'.'/'.$field->getName();
                //$field->setAttribute('data-parent', $record->class);
            }
            if ($field instanceof HtmlEditorField) 
            {
                if ($this->htmlEditorHeight) {
                    $field->setRows($this->htmlEditorHeight);
                }
            } 
            else if ($field instanceof UploadField) 
            {
                // Rewrite UploadField's "Select file" iframe to go through
                // this field.
                $urlSelectDialog = $field->getConfig('urlSelectDialog');
                if (!$urlSelectDialog) {
                    $urlSelectDialog = Controller::join_links('addinlinerecord', $record->class, $field->name, 'select'); // NOTE: $field->Link('select') without Form action link.
                }
                $field->setConfig('urlSelectDialog', $this->Link($urlSelectDialog));
                //$field->setAutoUpload(false);
            }

            // NOTE(Jake): Required to support UploadField
            if (method_exists($field, 'setRecord')) {
                $field->setRecord($record);
            }

            $currentFieldListModifying->push($field);
        }

        $resultFieldList = new FieldList();
        $resultFieldList->push($tab);
        $resultFieldList->setForm($this->form);
        return $resultFieldList;
    }

    /*public function addRecord(DataObjectInterface $record, $parentFields = null)
    {
        $fields = null;
        if (!$record->canEdit()) {
            return;
        }
        $this->list->push($record);

        $fields = $this->getRecordDataFields($record);

        foreach ($fields as $field) {
            // if it looks like a multieditor field, let's skip for now.
            if (strpos($field->getName(), '__') > 0) {
                continue;
            }

            // re-write the name to the multirecordediting name for later retrieval.
            // this cannot be done earlier as otherwise, fields that load data from the
            // record won't be able to find the information they're expecting
            $name = $this->getFieldName($field, $record);
            $field->setName($name);

            if ($tab) {
                $tab->push($field);
            }

            $this->children->push($field);
        }
    }*/

    /**
     * @param string|FormField $field
     * @param DataObject $record
     * @return string
     */
    protected function getFieldName($field, $record)
    {
        $name = $field instanceof FormField ? $field->getName() : $field;
        $recordID = static::get_field_id($record);

        return sprintf(
            '%s__%s__%s__%s__%s', $this->getName(), 'MultiRecordEditingField', $record->ClassName, $recordID, $name
        );
    }

    public function saveInto(\DataObjectInterface $record)
    {
        //$v = $this->Value();
        // Save existing has_one/has_many/many_many records
        /*$allItems = array();
        foreach ($this->children as $field) {
            $fieldname = $field->getName();
            if (strpos($fieldname, '__') > 0) {
                $bits = array_reverse(explode('__', $fieldname));
                if (count($bits) > 3) {
                    list($dataFieldName, $id, $classname) = $bits;
                    if (!isset($allItems["$classname-$id"])) {
                        $item                       = $this->list->filter(array('ClassName' => $classname, 'ID' => $id))->first();
                        $allItems["$classname-$id"] = $item;
                    }
                    $item = $allItems["$classname-$id"];
                    if ($item) {
                        if ($field) {
                            $field->setName($dataFieldName);
                            $field->saveInto($item);
                        }
                    }
                }
            }
        }
        foreach ($allItems as $item) {
            $item->write();
        }*/

        // Create and save new has_many/many_many records
        if (Controller::has_curr())
        {
            $class_id_field = array();
            $relationFieldName = $this->getName();
            $sortFieldName = $this->getSortFieldName();

            /**
             * @var SS_HTTPRequest
             */
            $request = Controller::curr()->getRequest();

            //Debug::dump($request); 

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

                    // From eg. 'new_1', check to ensure 'new' is there and retrieve
                    // the ID.
                    /*($new_id_arr = explode('_', $new_id);
                    if (!isset($new_id_arr[0]) && $new_id_arr[0] !== 'new') {
                        // todo(Jake): better error msg.
                        throw new Exception('Missing "new" keyword.');
                    }
                    if (!isset($new_id_arr[1])) {
                        // todo(Jake): better error msg.
                        throw new Exception('Missing id of new record.');
                    }
                    $id = $new_id_arr[1];*/

                    //
                    if ($fieldParametersCount == $FIELD_PARAMETERS_SIZE)
                    {
                        // 1st Nest Level
                        $class_id_field[$parentFieldName][$class][$new_id][$fieldName] = $value;
                    }
                    else
                    {
                        // 2nd, 3rd, nth Nest Level
                        $relationArray = &$class_id_field;
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
                        $relationArray[$fieldName] = $value; 
                        unset($relationArray);
                    }
                }
            }

            Debug::dump($class_id_field);
            exit(__FUNCTION__);

            foreach ($class_id_field as $class => $subRecordsData)
            {
                // Get fields used by DataObject so we can use $field->saveInto
                // to be as consistent as possible with normal operations.
                $subRecord = $class::create();
                $fields = $this->getRecordDataFields($subRecord);
                unset($subRecord);
                if (!count($fields)) {
                    // todo(Jake): better error msg.
                    throw new Exception('Cannot save new record. Mismatch.');
                }

                // Setup list to manipulate on $record based on the relation name.
                $listOrDataObject = $record->$relationFieldName();
                $list = new ArrayList();
                if ($listOrDataObject instanceof DataObject) {
                    // todo(Jake): Rewrite to use 'getMultiRecordEditingFieldList' function
                    if ($listOrDataObject instanceof WidgetArea) {
                        // NOTE(Jake): WidgetArea is supported for native Elemental support.
                        $list = $listOrDataObject->Widgets();
                    } else {
                        throw new Exception('Cannot add multiple records to "'.$relationFieldName.'" as its not a WidgetArea or SS_List.');
                    }
                } else if ($listOrDataObject instanceof SS_List) {
                    $list = $listOrDataObject;
                } else {
                    throw new Exception('Unable to work with relation field "'.$relationFieldName.'".');
                }

                // Create and add records to list
                foreach ($subRecordsData as $i => $subRecordData)
                {
                    $subRecord = $class::create();
                    foreach ($subRecordData as $fieldName => $value)
                    {
                        if ($sortFieldName !== $fieldName && 
                            !isset($fields[$fieldName]))
                        {
                            // todo(Jake): better error msg.
                            throw new Exception('Missing field');
                        }

                        $field = $fields[$fieldName];
                        $field->setValue($value);
                        $field->saveInto($subRecord);
                    }

                    // Handle sort if its not manually handled on the form
                    if (!isset($fields[$sortFieldName]))
                    {
                        $sortValue = $i; // Default to order added
                        if (isset($subRecordData[$sortFieldName])) {
                            $sortValue = $subRecordData[$sortFieldName];
                        }
                        if ($sortValue)
                        {
                            $subRecord->{$sortFieldName} = $sortValue;
                        }
                    }
                    
                    if (!$subRecord->doValidate())
                    {
                        // todo(Jake): better error msg.
                        throw new ValidationException('Failed validation');
                    }
                    $list->push($subRecord);
                }
            }
        }

        Debug::dump($list->toArray());
        exit(__FUNCTION__);

        parent::saveInto($record);
    }

    /**
     * @return FieldList
     */
    public function Fields() {
        if ($this->getUseToggles()) {
            return $this->tabs;
        }
        return $this->children;
    }

    /**
     * @return FieldList
     */
    public function Actions() {
        // todo(Jake): move to constructor and remove fields if no inline editing
        $modelClasses = $this->getModelClassesOrThrowExceptionIfEmpty();
        $modelFirstClass = key($modelClasses);

        $fields = FieldList::create();
        $fields->unshift(LiteralField::create($this->getName().'_clearfix', '<div class="clear"><!-- --></div>'));
        $fields->unshift($inlineAddButton = FormAction::create($this->getName().'_addinlinerecord', 'Add')
                            ->addExtraClass('js-multirecordediting-add-inline')
                            ->setUseButtonTag(true));

        // Setup default inline field data attributes
        $inlineAddButton->setAttribute('data-name', $this->getName());
        $inlineAddButton->setAttribute('data-action', $this->getName());
        $inlineAddButton->setAttribute('data-class', $modelFirstClass);
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
     * @return FieldList
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     *
     */
    public function getSortFieldName() {
        if ($this->sortFieldName) {
            // todo(Jake): add setSortFieldName
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
        $sort = str_replace('"', '', $sort); // Strip " as some modules use them for 'default_sort'
        if (strpos($sort, '.') !== FALSE) {
            throw new Exception('Cannot use relational sort field with '.__CLASS__.', default_sort config for "'.$baseClass.'" is incompatible.');
        }

        return $sort;
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

    public function FieldHolder($properties = array()) {
        if ($this->canAddInline)
        {
            // NOTE(Jake): jQuery.ondemand is required to allow FormField classes to add their own
            //             Requirements::javascript on-the-fly.
            Requirements::css(MULTIRECORDEDITOR_DIR.'/css/MultiRecordEditingField.css');
            Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui.css');
            Requirements::javascript(FRAMEWORK_DIR . '/thirdparty/jquery-ui/jquery-ui.js');
            Requirements::javascript(FRAMEWORK_DIR . '/javascript/jquery-ondemand/jquery.ondemand.js');
            Requirements::javascript(THIRDPARTY_DIR . '/jquery-entwine/dist/jquery.entwine-dist.js');
            Requirements::javascript(MULTIRECORDEDITOR_DIR.'/javascript/MultiRecordEditingField.js');
        }

        //Debug::dump("RENDERED"); exit;
        
        // Expose tabs to the template (as it's protected)
        //$properties['Tabs'] = $this->tabs; // todo(jake): remove no longer needed?
        return parent::FieldHolder($properties);
    }
}