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
     * @var
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
     * The field to sort by.
     *
     * @var string|array
     */
    protected $sortField = null;

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

    private static $allowed_actions = array(
        'addinlinerecord'
    );

    public function __construct($name, $title = null, $recordList = null)
    {
        parent::__construct($name, $title);

        $this->children = FieldList::create();

        $this->tabs = FieldList::create();
        $this->originalList = $recordList;
        $this->list = ArrayList::create();
        if ($recordList) {
            foreach ($recordList as $record) {
                $this->addRecord($record);
            }
        }
    }

    /**
     * Action
     */
    public function addinlinerecord(SS_HTTPRequest $request) {
        // Force reset
        $this->children = FieldList::create();
        $this->tabs = FieldList::create();
        $this->list = ArrayList::create();

        $class = $request->requestVar('ClassName');
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
        $this->addRecord($record);

        return $this->renderWith(array('MultiRecordEditingField_addinline'));
    }

    public function isComposite()
    {
        return true; // parent::isComposite();
    }
	
	public function replaceField($fieldName, $newField) {
		// noop for a mr editing field... for now
	}

    public function collateDataFields(&$list, $saveableOnly = false)
    {
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

    public function removeByName($fieldName, $dataFieldOnly = false)
    {
        return $this->children->removeByName($fieldName, $dataFieldOnly);
    }

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
    public function getModelClassesOrException() {
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
    public function getRecords()
    {
        Deprecation::notice('2.0', __FUNCTION__.' is deprecated. Use getList instead. Change made to keep this field more consistent with GridField API.');
        return $this->list;
    }

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
    public function editList($list)
    {
        if ($list) {
            foreach ($list as $record) {
                if ($record instanceof DataObjectInterface) {
                    $this->addRecord($record);
                }
            }
        }
    }

    /**
     * @return FieldList|null
     */
    public function getRecordDataFields(DataObjectInterface $record) {
        if (method_exists($record, 'multiEditFields')) {
            $fields = $record->multiEditFields();
        } else {
            $fields = $record->getCMSFields();
        }
        $record->extend('updateMultiEditFields', $fields);
        $fields = $fields->dataFields();
        return $fields;
    }

    public function addRecord(DataObjectInterface $record, $parentFields = null)
    {
        /**
         * @var $fields FieldList 
         */
        $fields = null;
        if (!$record->canEdit()) {
            return;
        }
        $this->list->push($record);

        if (method_exists($record, 'multiEditor')) {
            $editor = $record->multiEditor();
            // add its records to 'me'
            $this->addMultiEditor($editor, $record, true);
            return;
        }

        $fields = $this->getRecordDataFields($record);
        if (!count($fields)) {
            return;
        }

        $status = ($record->ID) ? $record->CMSPublishedState : 'New';
        if ($status) {
            $status = ' ('.$status.')';
        }

        $recordID = static::get_field_id($record);

        $tab = ToggleCompositeField::create('CompositeHeader'.$recordID, $record->Title.$status, null);
        if ($parentFields) {
            $parentFields->push($tab);
        } else {
            $tab->setStartClosed(false);
            $this->tabs->push($tab);
        }

        // if we're not using toggles, we only add the header _if_ we're an inner item, ie $parentFields != null
        if ($parentFields) {
            $this->children->push(HeaderField::create('RecordHeader'.$recordID, $record->Title.$status));
        }

        foreach ($fields as $field) {
            $original = $field->getName();

            // if it looks like a multieditor field, let's skip for now.
            if (strpos($original, '__') > 0) {
                continue;
            }

            if ($field instanceof MultiRecordEditingField) {
                $this->addMultiEditor($field, $record, false, $tab);
                continue;
            }

            $exists = (
                isset($record->$original) ||
                $record->hasMethod($original) ||
                ($record->hasMethod('hasField') && $record->hasField($original))
                );

            $val = null;
            if ($exists) {
                $val = $record->__get($original);
            }

            $field->setValue($val, $record);

            // tweak HTMLEditorFields so they're not huge
            if ($this->htmlEditorHeight && $field instanceof HtmlEditorField) {
                $field->setRows($this->htmlEditorHeight);
            }

            // re-write the name to the multirecordediting name for later retrieval.
            // this cannot be done earlier as otherwise, fields that load data from the
            // record won't be able to find the information they're expecting
            $name = $this->getFieldName($field, $record);
            $field->setName($name);

            if (method_exists($field, 'setRecord')) {
                $field->setRecord($record);
            }

            if ($tab) {
                $tab->push($field);
            }

            $this->children->push($field);
        }
    }

    protected function addMultiEditor($editor, $fromRecord, $addHeader = false, $tab = null)
    {
        if ($addHeader) {
            $this->children->push(HeaderField::create('RecordHeader'.static::get_field_id($fromRecord), $fromRecord->Title));
        }

        foreach ($editor->getList() as $r) {
            $this->addRecord($r, $tab);
        }
    }

    protected function getFieldName($field, $record)
    {
        $name = $field instanceof FormField ? $field->getName() : $field;
        $recordID = static::get_field_id($record);

        return sprintf(
            '%s__%s__%s__%s', $this->getName(), $record->ClassName, $recordID, $name
        );
    }

    public function saveInto(\DataObjectInterface $record)
    {
        $v = $this->Value();

        // Save existing has_one/has_many/many_many records
        $allItems = array();
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
        }

        // Create and save new has_many/many_many records
        if (Controller::has_curr())
        {
            $class_id_field = array();
            $relationFieldName = $this->getName();
            $sortFieldName = $this->getSortField();

            /**
             * @var SS_HTTPRequest
             */
            $request = Controller::curr()->getRequest();
            foreach ($request->requestVars() as $name => $value)
            {
                // If fieldName is the -very- first part of the string
                // NOTE(Jake): Check is required here as we're pulling straight from $request
                if (strpos($name, $relationFieldName) === 0)
                {
                    $fieldParameters = explode('__', $name);
                    if (!isset($fieldParameters[3])) {
                        // You expect a name like 'ElementArea__ElementContent__3__Title'
                        // So ensure 4 parameters exist in the name, otherwise continue.
                        continue;
                    }

                    $class = $fieldParameters[1];
                    $new_id = $fieldParameters[2];
                    $fieldName = $fieldParameters[3];

                    // From eg. 'new_1', check to ensure 'new' is there and retrieve
                    // the ID.
                    $new_id_arr = explode('_', $new_id);
                    if (!isset($new_id_arr[0]) && $new_id_arr[0] !== 'new') {
                        // todo(Jake): better error msg.
                        throw new Exception('Missing "new" keyword.');
                    }
                    if (!isset($new_id_arr[1])) {
                        // todo(Jake): better error msg.
                        throw new Exception('Missing id of new record.');
                    }
                    $id = $new_id_arr[1];
                    $class_id_field[$class][$id][$fieldName] = $value;
                    // todo(Jake): 
                    // Create blank record
                    /*$record = $class::create();
                    $fields = $this->getRecordDataFields($record);
                    if (!count($fields)) {
                        return;
                    }

                    foreach ($fields as $field)
                    {

                        $field->saveInto($record);
                    }
                    Debug::dump($record);*/
                }
            }

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
    public function getChildren() {
        return $this->children;
    }

    /**
     *
     */
    public function getSortField() {
        if ($this->sortField) {
            // todo(Jake): add setSortField
            return $this->sortField;
        }

        $modelClasses = $this->getModelClassesOrException();
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

    public function FieldHolder($properties = array())
    {
        if ($this->canAddInline)
        {
            $modelClasses = $this->getModelClassesOrException();
            $modelFirstClass = key($modelClasses);
            // NOTE(Jake): jQuery.ondemand is required to allow FormField classes to add their own
            //             Requirements::javascript on-the-fly.
            Requirements::javascript(FRAMEWORK_DIR . '/javascript/jquery-ondemand/jquery.ondemand.js');
            Requirements::javascript(MULTIRECORDEDITOR_DIR.'/javascript/MultiRecordEditingField.js');

            $fields = $this->Fields();
            $fields->unshift(LiteralField::create($this->getName().'_clearfix', '<div class="clear"><!-- --></div>'));
            $fields->unshift(FormAction::create($this->getName().'_addinlinerecord', 'Add')
                                ->setAttribute('data-name', $this->getName())
                                ->setAttribute('data-class', $modelFirstClass)
                                ->addExtraClass('js-multirecordediting-add-inline')
                                ->setUseButtonTag(true));
            if (count($modelClasses) > 1) 
            {
                $fields->unshift($classField = DropdownField::create($this->getName().'_ClassName', ' ')
                                                ->addExtraClass('js-multirecordediting-classname')
                                                ->setEmptyString('(Select section type to create)'));
                $classField->setSource($modelClasses);
            }

            // Insert elements via javascript before this element when added inline.
            $fields->push(LiteralField::create($this->getName().'_insertpoint', '<div data-name="'.$this->getName().'" class="js-multirecordediting-insertpoint_'.$this->getName().'"></div>'));
        }

        //Debug::dump("RENDERED"); exit;
        
        // Expose tabs to the template (as it's protected)
        //$properties['Tabs'] = $this->tabs; // todo(jake): remove no longer needed?
        return parent::FieldHolder($properties);
    }
}