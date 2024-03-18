<?php

namespace Leochenftw\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use Ramsey\Uuid\Uuid;

class GuidExtension extends DataExtension
{
    private static $db = [
        'GUID' => 'Varchar(128)',
    ];

    private static $indexes = [
        'GUID' => 'unique',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $guid = $fields->fieldByName('Root.Main.GUID');
        $guid->setReadonly(true);

        return $fields;
    }

    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->owner->GUID = $this->populateGUID();
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (empty($this->owner->GUID)) {
            $this->owner->GUID = $this->populateGUID();
        }
    }

    private function populateGUID()
    {
        $uuid = Uuid::uuid4();
        return $uuid->toString();
    }
}
