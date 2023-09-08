<?php

namespace Leochenftw\Task;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\BuildTask;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;
use SilverStripe\Core\Environment;

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

    /**
     * This method called via the TaskRunner
     *
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        $tableName = $this->config()->dynamodb_table;

        $region = Environment::getEnv('AWS_REGION');
        $version = Environment::getEnv('AWS_VERSION');
        $version = empty($version) ? 'latest' : $version;
        $key = Environment::getEnv('AWS_ACCESS_KEY_ID');
        $secret = Environment::getEnv('AWS_SECRET_ACCESS_KEY');

        if (
            empty($region)
            || empty($key)
            || empty($secret)
        ) {
            throw new \Exception('You have missing env var(s). Please check your .env file');
        }
        
        $configs = [
            'region' => $region,
            'version' => $version,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ];

        $client = new DynamoDbClient($configs);

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
                        'TableName' => $tableName,
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
