# Multi Record Editing field

A field for editing multiple fields in a single editing interface

## Requirements

 * SilverStripe ^3.2
 
## Installation


## License
See [License](license.md)


## Documentation
 
```
private static $has_many = array('Cells', 'BasicContent');

$editor = MultiRecordEditingField::create('ContentCellEditor', 'Content Cells', $this->Cells());
$fields->addFieldToTab('Root.ContentCells', $editor);
```


## Example configuration (optional)

```php
class Page extends SiteTree {
	
	private static $has_many = array(
        'Cells'      => 'BasicContent',
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $editor = MultiRecordEditingField::create('ContentCellEditor', 'Content Cells', $this->Cells());
        $fields->addFieldToTab('Root.ContentCells', $editor);

        if (Permission::check('ADMIN')) {
            $config = GridFieldConfig_RecordEditor::create();
            $grid = GridField::create('Cells', 'Cells', $this->Cells(), $config);
            $fields->addFieldToTab('Root.ContentCells', $grid);
        }

        return $fields;
    }
}

class Page_Controller extends ContentController {}


class BasicContent extends DataObject
{
    private static $db = array(
        'Title'     => 'Varchar(255)',
        'Description'   => 'Text',
        'Content'       => 'HTMLText',
    );

    private static $has_one = array(
        'Parent'        => 'Page',
    );

    private static $many_many = array(
        'Images'        => 'Image',
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $uploadField = UploadField::create('Images', 'Images', $this->Images());
        $uploadField->setAllowedFileCategories('image');
        $fields->replaceField('Images', $uploadField);

        return $fields;
    }
}

```

## Maintainers

* Marcus Nyeholt <marcus@silverstripe.com.au>
 
## Bugtracker
