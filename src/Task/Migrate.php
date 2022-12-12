<?php

namespace Leochenftw\Task;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\BuildTask;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;

class Migrate extends BuildTask
{
    /**
     * @var bool $enabled If set to FALSE, keep it from showing in the list
     * and from being executable through URL or CLI.
     */
    protected $enabled = true;

    /**
     * @var string $title Shown in the overview on the TaskRunner
     * HTML or CLI interface. Should be short and concise, no HTML allowed.
     */
    protected $title = 'DynamoDB Migrator';
    private static $segment = 'dynamodb-migrate';
    private static $classes_to_migrate = [];

    /**
     * This method called via the TaskRunner
     *
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        $client = new DynamoDbClient([
            'region'       => 'ap-southeast-2',
            'version'      => 'latest',
        ]);

        $marshaler = new Marshaler();

        $classes = [];

        foreach ($this->config()->classes_to_migrate as $class) {
            if ($class::singleton()->hasMethod('getDynamoDbMapping')) {
                $classes[] = $class;
            }
        }

        foreach ($classes as $class) {
            echo "Reading {$class}..." . PHP_EOL;
            $list = $class::get();
            $lastItem = null;
            foreach ($list as $item) {
                $title = !empty($item->DynamoDbMapping['Title'])
                    ? $item->DynamoDbMapping['Title']
                    : ('#' . $item->DynamoDbMapping['ID'])
                ;

                try {
                    $client->putItem([
                        'TableName' => 'MerchantCloud',
                        'Item'      => $marshaler->marshalItem($item->DynamoDbMapping),
                    ]);
                    $lastItem = $item->DynamoDbMapping;
                } catch (DynamoDbException $e) {
                    Debug::dump($lastItem);
                    Debug::dump($item->DynamoDbMapping);
                    die;
                }

                echo "$title has been migrated." . PHP_EOL;
            }
        }
    }
}
