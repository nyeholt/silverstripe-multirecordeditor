<?php

class MultiRecordTransformation extends FormTransformation {
	public function transform(FormField $field) {
		if (!$field instanceof GridField)
		{
			throw new Exception(__CLASS__.' requires GridField FormField type.');
		}
		$title = $field->Title();
		$list = $field->getList();
		$config = $field->getConfig();
		$result = MultiRecordField::create($field->getName(), $title, $list);
		
		// Support: GridFieldExtensions (https://github.com/silverstripe-australia/silverstripe-gridfieldextensions)
		$gridFieldAddNewMultiClass = $config->getComponentsByType('GridFieldAddNewMultiClass')->first();
		if ($gridFieldAddNewMultiClass) {
			$classes = $gridFieldAddNewMultiClass->getClasses($field);
			$result->setModelClasses($classes);
		}
		return $result;
	}
}

