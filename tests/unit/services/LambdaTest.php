<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\fluxtests\unit\services;

use Aws\Lambda\LambdaClient;
use Aws\MockHandler;
use Aws\Result;
use Codeception\Stub\Expected;
use craft\helpers\App;
use craft\test\TestCase;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;
use dyerc\flux\services\Lambda;

class LambdaTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    private function mockClient($response): LambdaClient
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $mock = new MockHandler();
        $mock->append(new Result($response));

        return new LambdaClient([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => App::parseEnv($settings->awsAccessKeyId),
                'secret' => App::parseEnv($settings->awsSecretAccessKey)
            ],
            'handler' => $mock
        ]);
    }

    public function testParsesFunctionStatus()
    {
        Flux::getInstance()->set('lambda', $this->make(Lambda::class, [
            'client' => Expected::atLeastOnce(function () {
                return $this->mockClient([
                    'Configuration' => [
                        'Description' => "Deployed by Flux v4.0.0 7e696161f558ab98fc2e43202516934c",
                        'Role' => 'role',
                        'FunctionArn' => 'arn',
                        'MemorySize' => '512',
                        'LastModified' => 'ff'
                    ]
                ]);
            })
        ]));

        $status = Flux::getInstance()->lambda->getStatus("function");

        $this->assertSame($status['version'], "4.0.0");
        $this->assertSame($status['config'], "7e696161f558ab98fc2e43202516934c");
    }

    public function testParsesBetaFunctionStatus()
    {
        Flux::getInstance()->set('lambda', $this->make(Lambda::class, [
            'client' => Expected::atLeastOnce(function () {
                return $this->mockClient([
                    'Configuration' => [
                        'Description' => "Deployed by Flux v4.0.0-beta.1 7e696161f558ab98fc2e43202516934",
                        'Role' => 'role',
                        'FunctionArn' => 'arn',
                        'MemorySize' => '512',
                        'LastModified' => 'ff'
                    ]
                ]);
            })
        ]));

        $status = Flux::getInstance()->lambda->getStatus("function");

        $this->assertSame($status['version'], "4.0.0-beta.1");
        $this->assertSame($status['config'], "7e696161f558ab98fc2e43202516934");
    }
}