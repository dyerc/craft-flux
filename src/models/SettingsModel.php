<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;

class SettingsModel extends Model
{
    /**
     * @var bool Intercept image transforms and route them through CloudFront
     */
    public bool $enabled = false;

    /**
     * @var bool Ensure requests originated from Craft by appending an HMAC verification token
     */
    public bool $verifyQuery = true;

    /**
     * @var string HMAC secret used if `verifyQuery` is enabled. If blank, a secret will be automatically generated when Flux is first installed
     */
    public string $verifySecret = "";

    /**
     * @var bool For local asset file systems, store a duplicate in S3 to speed up processing asset variations
     */
    public bool $cacheEnabled = true;

    /**
     * @var bool When an asset is changed, automatically submit a CloudFront invalidation and purge any transformed files
     */
    public bool $autoPurgeAssets = true;

    /**
     * @var string AWS Access Key ID
     */
    public string $awsAccessKeyId = "";

    /**
     * @var string AWS Secret Access Key
     */
    public string $awsSecretAccessKey = "";

    /**
     * @var string Prefix for auto generated AWS resources
     */
    public string $awsResourcePrefix = "Flux";

    /**
     * @var string Prefix for auto generated global AWS resources
     */
    public string $awsGlobalResourcePrefix = "";

    /**
     * @var string Bucket selection mode ('choose' or 'manual')
     */
    public string $bucketSelectionMode = 'choose';

    /**
     * @var string
     */
    public string $distributionSelectionMode = 'choose';

    /**
     * @var string S3 Bucket name
     */
    public string $awsBucket = "";

    /**
     * @var string CloudFront distribution ID
     */
    public string $cloudFrontDistributionId = "";

    /**
     * @var string CloudFront domain
     */
    public string $cloudFrontDomain = "";

    /**
     * @var string S3 bucket region
     */
    public string $awsRegion = "";

    /**
     * @var string S3 bucket root prefix
     */
    public string $rootPrefix = "Flux";

    /**
     * @var bool Automatically serve WebP files if the users browser supports it via the Accept header
     */
    public bool $acceptWebp = true;

    /**
     * @var int Default JPG transform quality unless specified
     */
    public int $jpegQuality = 80;

    /**
     * @var int Default WebP transform quality unless specified
     */
    public int $webpQuality = 80;

    /**
     * @var bool Log more detailed information to CloudWatch
     */
    public bool $loggingEnabled = false;

    /**
     * @var int Maximum memory origin response function can use
     */
    public int $lambdaMemory = 512;

    /**
     * @var int Maximum time origin response function can run
     */
    public int $lambdaTimeout = 15;


    public function isAwsConfigured(): bool
    {
        return !empty($this->awsAccessKeyId) && !empty($this->awsSecretAccessKey) && !empty($this->awsBucket) && !empty($this->cloudFrontDistributionId);
    }

    public function getCloudfrontEndpoint(): string
    {
        $root = "https://" . App::parseEnv($this->cloudFrontDomain) . '/';
        return $root;
    }

    public function configurationReinstallRequired(array $newSettings): bool
    {
        $sensitive = ["loggingEnabled", "rootPrefix", "verifyQuery", "cacheEnabled", "awsBucket", "cloudFrontDistributionId", "cloudFrontDomain", "awsRegion", "jpegQuality", "webpQuality", "acceptWebp", "lambdaMemory", "lambdaTimeout"];

        foreach ($sensitive as $key) {
            if (key_exists($key, $newSettings) && $newSettings[$key] != $this->{$key}) {
                return true;
            }
        }

        return false;
    }

    public function lambdaConfig(): array
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();

        $sources = array_map(function ($volume) {
            if (is_a($volume->fs, "craft\\awss3\\Fs") && $volume->fs->settings['bucket'] == App::parseEnv($this->awsBucket)) {
                $fs = $volume->fs;

                return [
                    'type' => 'local',
                    'handle' => $volume->handle,
                    'region' => $fs->settings['region'],
                    'bucket' => $fs->settings['bucket'],
                    'subFolder' => $fs->settings['subfolder']
                ];
            } else {
                return [
                    'type' => 'remote',
                    'handle' => $volume->handle,
                    'url' => $volume->fs->getRootUrl()
                ];
            }
        }, $volumes);

        return [
            'loggingEnabled' => $this->loggingEnabled,
            'rootPrefix' => App::parseEnv($this->rootPrefix),
            'sources' => $sources,

            'verifyQuery' => $this->verifyQuery,
            'verifySecret' => $this->verifySecret,

            'cacheEnabled' => $this->cacheEnabled,

            'bucket' => App::parseEnv($this->awsBucket),
            'region' => App::parseEnv($this->awsRegion),

            'jpegQuality' => $this->jpegQuality,
            'webpQuality' => $this->webpQuality,

            'acceptWebp' => $this->acceptWebp
        ];
    }

    public function lambdaConfigHash(): string
    {
        return md5(json_encode($this->lambdaConfig()));
    }

    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['awsAccessKeyId', 'awsSecretAccessKey', 'awsGlobalResourcePrefix', 'awsResourcePrefix', 'rootPrefix'],
            ],
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['awsAccessKeyId', 'awsSecretAccessKey'], 'required'],
            [['awsGlobalResourcePrefix', 'awsResourcePrefix', 'rootPrefix'], 'match', 'pattern' => '/^[a-zA-Z0-9\-]*$/i'],
            [['jpegQuality', 'webpQuality'], 'number', 'min' => 1, 'integerOnly' => true, 'max' => 100],
            [['lambdaMemory'], 'number', 'min' => 128, 'integerOnly' => true, 'max' => 10240],
            [['lambdaTimeout'], 'number', 'min' => 3, 'integerOnly' => true, 'max' => 900],
        ];
    }
}