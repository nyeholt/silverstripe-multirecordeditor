<?php

// Default CMS HTMLEditorConfig
define('MULTIRECORDEDITOR_DIR',basename(dirname(__FILE__)));

// Clone 'cms' config as much as possible.
$defaultConfig = HtmlEditorConfig::get('cms');
$defaultOptions = array();
$defaultPlugins = array();
foreach (array('friendly_name', 'priority', 'body_class', 'document_base_url', 
		       'cleanup_callback', 'use_native_selects', 'valid_elements', 'extended_valid_elements') as $option)
{
	$defaultOptions[$option] = $defaultConfig->getOption($option);
}
foreach ($defaultConfig->getPlugins() as $k => $v)
{
	$defaultPlugins[$k] = $v;
}
foreach (array('multirecordediting', 'multirecordediting_minimal') as $k)
{
	$config = HtmlEditorConfig::get($k);
	$config->setOptions($defaultOptions);
	$config->enablePlugins($defaultPlugins);
	// Set the others manually, no nice way to copy
	$config->insertButtonsBefore('formatselect', 'styleselect');
	$config->addButtonsToLine(2,
		'ssmedia', 'ssflash', 'sslink', 'unlink', 'anchor', 'separator','code', 'fullscreen', 'separator');
	$config->removeButtons('tablecontrols');
	$config->addButtonsToLine(3, 'tablecontrols');
}

// Remove most buttons for 'multirecordediting_basic' so the user can just 
// bold/italic/underline/strikethrough, change h1/h2/h3, and add <ul> / <ol>
$config = HtmlEditorConfig::get('multirecordediting_minimal');
$config->removeButtons('justifyleft', 'justifyright', 'justifycenter', 'justifyfull');
$config->removeButtons('indent', 'outdent', 'blockquote', 'hr', 'charmap');
$config->setButtonsForLine(2, array());
// todo(Jake): Factor these out to another module/site?
$config->disablePlugins('contextmenu');
$config->setOption('height', '250px');