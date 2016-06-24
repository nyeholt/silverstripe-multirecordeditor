# Multi Record Field

A field for editing multiple records in a single form.

## Requirements

 * SilverStripe ^3.2
 
## Installation


## License
See [License](license.md)

## Documentation
 
```
private static $has_many = array('Cells', 'BasicContent');

$editor = MultiRecordField::create('ContentCellEditor', 'Content Cells', $this->Cells());
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

        $editor = MultiRecordField::create('ContentCellEditor', 'Content Cells', $this->Cells());
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

**MultiRecordField Nesting**

The `MultiRecordField` supports nesting of other 
`MultiRecordField`s. When the field detects a `MultiRecordField` 
in the set of fields to edit, that field is added as another nested toggle 
field inside the parent set of fields for editing. 

**Custom fields**

The `MultiRecordField` uses the output of `getCMSFields` when building
the fieldlist used for editing. To provide an alternate set of fields, define
a `getMultiRecordFields` method that returns a `FieldList` object.

Additionally, the `MultiRecordField` calls the `updateMultiEditFields` 
extension hook on the _record_ being edited to allow extensions a chance to
change the fields. 

## Screenshots

[todo]

## Maintainers

* Jake Bentvelzen (SilbinaryWolf) <jake@silverstripe.com.au>
 
## Bugtracker
