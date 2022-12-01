<?php

namespace dyerc\fluxtests\unit\models;

use craft\test\TestCase;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;
use UnitTester;

class SettingsModelTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testValidatesLambdaConfig()
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $this->assertSame(
            $settings->lambdaConfigHash(),
            $settings->lambdaConfigHash()
        );
    }
}