<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use Aws\Lambda\Exception\LambdaException;
use Aws\Lambda\LambdaClient;
use Craft;
use craft\helpers\App;
use DateTime;
use DateTimeInterface;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;
use yii\base\Component;
use ZipArchive as ZipArchive;

class Lambda extends Component
{
    private LambdaClient|null $_client = null;

    /* @var string */
    public const VIEWER_REQUEST_FUNCTION_SUFFIX = "-Viewer-Request";

    /* @var string */
    public const ORIGIN_RESPONSE_FUNCTION_SUFFIX = "-Origin-Response";

    /* @var int */
    public const POLL_FREQUENCY = 3;

    public function client(): LambdaClient
    {
        if (!$this->_client) {
            /* @var SettingsModel */
            $settings = Flux::getInstance()->getSettings();

            $this->_client = new LambdaClient([
                'version' => 'latest',
                'region' => 'us-east-1', // Lambda@Edge must always be in us-east-1
                'credentials' => [
                    'key' => App::parseEnv($settings->awsAccessKeyId),
                    'secret' => App::parseEnv($settings->awsSecretAccessKey)
                ]
            ]);
        }

        return $this->_client;
    }

    public function getStatus(string $name): array|null
    {
        try {
            $function = $this->client()->getFunction([
                'FunctionName' => $name
            ]);

            preg_match("/\d\.\d\.\d(-\S*)*/", $function['Configuration']['Description'], $m);
            $version = empty($m) ? "Unknown" : $m[0];

            return [
                'name' => $name,
                'installed' => true,
                'role' => $function['Configuration']['Role'],
                'arn' => $function['Configuration']['FunctionArn'],
                'version' => $version,
                'memory' => $function['Configuration']['MemorySize'],
                'lastModified' => DateTime::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $function['Configuration']['LastModified'])
            ];
        } catch (LambdaException $e) {
            return null;
        }
    }

    public function getInstalledVersion(): string|null
    {
        $status = $this->getFunctionStatuses();

        if ($status['viewerRequest'] && $status['originResponse']) {
            return $status['viewerRequest']['version'] == $status['originResponse']['version'] ? $status['viewerRequest']['version'] : null;
        } else {
            return null;
        }
    }

    public function getFunctionStatuses(): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $prefix = App::parseEnv($settings->awsResourcePrefix);

        return [
            'viewerRequest' => $this->getStatus($prefix . self::VIEWER_REQUEST_FUNCTION_SUFFIX),
            'originResponse' => $this->getStatus($prefix . self::ORIGIN_RESPONSE_FUNCTION_SUFFIX)
        ];
    }

    public function purgeOldVersions(string $name, int $keep = 5)
    {
        $results = $this->client()->listVersionsByFunction([
            'FunctionName' => $name
        ]);

        // Only target numeric versions, ignoring anything named or $LATEST
        $versions = array_filter($results['Versions'], function ($v) {
            return is_numeric($v['Version']);
        });

        usort($versions, function ($a, $b) {
            return $b['Version'] <=> $a['Version'];
        });

        foreach (array_slice($versions, $keep) as $version) {
            $this->client()->deleteFunction([
                'FunctionName' => $version['FunctionArn']
            ]);
        }
    }

    /*
     * Create or update installed version of function, publish a new version
     * and return its data
     */
    public function deployFunction(string $name, string $zipBundle, array $overrides = []): array
    {
        $config = array_merge([
            'FunctionName' => $name,
            'Description' => "Deployed by Flux v" . Flux::getInstance()->version,
            'Runtime' => 'nodejs16.x'
        ], $overrides);

        $existingStatus = $this->getStatus($name);

        $roleArn = Flux::getInstance()->aws->updateOrCreateLambdaRole($name . Aws::ROLE_SUFFIX);

        // Update or create function if it doesn't yet exist
        if ($existingStatus) {
            $this->client()->updateFunctionConfiguration($config);

            $this->waitForLambdaToProcess($name);

            $this->client()->updateFunctionCode([
                'FunctionName' => $name,
                'ZipFile' => file_get_contents($zipBundle)
            ]);
        } else {
            $this->client()->createFunction(array_merge($config, [
                'Code' => [
                    'ZipFile' => file_get_contents($zipBundle)
                ],
                'Handler' => "index.handler",
                'Role' => $roleArn
            ]));
        }

        $this->waitForLambdaToProcess($name);

        // Publish new version
        $version = $this->client()->publishVersion(['FunctionName' => $name]);

        if ($version['LastUpdateStatus'] != "Successful") {
            Craft::error("Lambda upload unsuccessful. Status is " . $version['LastUpdateStatus']);
        }

        $this->purgeOldVersions($name);

        return [
            'arn' => $version['FunctionArn'],
            'role' => $version['Role'],
        ];
    }

    public function deployViewerRequestFunction(): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $bundle = $this->packageFunctionBundle(
            Craft::getAlias('@dyerc/flux/lambda/request/index.js')
        );
        $func = $this->deployFunction(
            App::parseEnv($settings->awsResourcePrefix) . self::VIEWER_REQUEST_FUNCTION_SUFFIX,
            $bundle,
            [ 'MemorySize' => 128 ]
        );

        unlink($bundle);
        return $func;
    }

    public function deployOriginResponseFunction(): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $bundle = $this->packageFunctionBundle(
            Craft::getAlias('@dyerc/flux/lambda/response/index.js'),
            Craft::getAlias('@dyerc/flux/lambda/response/node_modules.zip')
        );
        $func = $this->deployFunction(
            App::parseEnv($settings->awsResourcePrefix) . self::ORIGIN_RESPONSE_FUNCTION_SUFFIX,
            $bundle,
            [
                'MemorySize' => $settings->lambdaMemory,
                'Timeout' => $settings->lambdaTimeout
            ]
        );

        unlink($bundle);
        return $func;
    }

    public function publishNewVersion(array $viewerRequest, array $originResponse)
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $prefix = App::parseEnv($settings->awsResourcePrefix);
        $distributionId = App::parseEnv($settings->cloudFrontDistributionId);

        $functions = [
            [
                'EventType' => 'viewer-request',
                'IncludeBody' => false,
                'LambdaFunctionARN' => $viewerRequest['arn']
            ],
            [
                'EventType' => 'origin-response',
                'IncludeBody' => false,
                'LambdaFunctionARN' => $originResponse['arn']
            ]
        ];

        Flux::getInstance()->s3->checkAndFixBucketPolicy([
            $viewerRequest['role'],
            $originResponse['role']
        ]);

        $cloudfront = Flux::getInstance()->cloudfront->client();

        $cachePolicyId = Flux::getInstance()->cloudfront->updateCachePolicy("$prefix-Cache-Policy");
        $originRequestPolicyId = Flux::getInstance()->cloudfront->updateOriginRequestPolicy("$prefix-Origin-Request-Policy");

        $distribution = $cloudfront->getDistribution(['Id' => $distributionId]);

        $updatedConfig = $distribution['Distribution'];

        $updatedConfig['DistributionConfig']['DefaultCacheBehavior']['LambdaFunctionAssociations']['Quantity'] = count($functions);
        $updatedConfig['DistributionConfig']['DefaultCacheBehavior']['LambdaFunctionAssociations']['Items'] = $functions;
        $updatedConfig['DistributionConfig']['DefaultCacheBehavior']['CachePolicyId'] = $cachePolicyId;
        $updatedConfig['DistributionConfig']['DefaultCacheBehavior']['OriginRequestPolicyId'] = $originRequestPolicyId;
        $updatedConfig['Id'] = $distributionId;
        $updatedConfig['IfMatch'] = $distribution['ETag'];

        $updatedConfig['DistributionConfig']['Origins']['Items'] = array_map(function($item) use ($settings) {
            if (strlen($settings->rootPrefix) > 0) {
                $item['OriginPath'] = "/" . App::parseEnv($settings->rootPrefix);
            } else {
                $item['OriginPath'] = "";
            }
            return $item;
        }, $updatedConfig['DistributionConfig']['Origins']['Items']);

        $cloudfront->updateDistribution($updatedConfig);
    }

    private function waitForLambdaToProcess(string $name)
    {
        $updated = false;
        $i = 0;

        while (!$updated && $i < 60) {
            sleep(3);

            $response = $this->client()->getFunction(['FunctionName' => $name]);

            if ($response['LastUpdateStatus'] != 'InProgress') {
                $updated = true;
            }

            $i++;
        }
    }

    private function buildInjectedConfig(): string
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $compiledDate = date("F j, Y, g:i a");
        $config = json_encode($settings->lambdaConfig());

        return "// Compiled by Flux on $compiledDate\n\nglobal.fluxConfig=$config;\n\n";
    }

    // Return zip file of bundle
    private function packageFunctionBundle(string $script, string|null $nodeModules = null): string
    {
        $zip = new ZipArchive();
        $zipFile = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . "function.zip";

        // Duplicate an existing template zip file if we're using one
        if ($nodeModules) {
            copy($nodeModules, $zipFile);
        }

        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            Craft::error("Unable to create zip file: " . $zipFile);
        }

        $scriptContents = $this->buildInjectedConfig() . file_get_contents($script);
        $zip->addFromString("index.js", $scriptContents);

        $zip->close();

        return $zipFile;
    }
}