<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\utilities;

use Aws\CloudFront\CloudFrontClient;
use Aws\Lambda\LambdaClient;
use Craft;
use craft\base\Utility;
use craft\helpers\App;
use dyerc\flux\Flux;
use dyerc\flux\helpers\PolicyHelper;
use dyerc\flux\jobs\LambdaDeployJob;
use dyerc\flux\models\SettingsModel;

class FluxUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('flux', 'Flux');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'flux';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): ?string
    {
        $iconPath = Craft::getAlias('@vendor/dyerc/craft-flux/src/icon-mask.svg');

        if (!is_string($iconPath)) {
            return null;
        }

        return $iconPath;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        if ($settings->isAwsConfigured()) {
            $lambda = self::lambdaStatus();
            $cloudfront = Flux::getInstance()->cloudfront->getStatus();
            $s3 = Flux::getInstance()->s3->getStatus(self::lambdaRoles($lambda));
            $info = [];

            if ($lambda['installed']) {
                $info[] = [
                    'label' => 'Viewer Request Function',
                    'value' => $lambda['functions']['viewerRequest']['name']
                ];
                $info[] = [
                    'label' => 'Origin Response Function',
                    'value' => $lambda['functions']['originResponse']['name']
                ];
                $info[] = [
                    'label' => 'Origin Response Function Memory',
                    'value' => $lambda['functions']['originResponse']['memory'] . "MB"
                ];
                $info[] = [
                    'label' => 'Lambda Function Version',
                    'value' => $lambda['version']
                ];
            }

            if ($cloudfront) {
                $info[] = [
                    'label' => 'CloudFront Distribution ID',
                    'value' => $cloudfront['id']
                ];
                $info[] = [
                    'label' => 'CloudFront Domain',
                    'value' => $cloudfront['domain']
                ];
            }

            if ($s3) {
                $info[] = [
                    'label' => 'S3 Bucket',
                    'value' => $s3['bucket']
                ];
                $info[] = [
                    'label' => "S3 Path",
                    'value' => App::parseEnv($settings->rootPrefix) . "/"
                ];
            }

            return Craft::$app->getView()->renderTemplate('flux/_utility', [
                'lambda' => $lambda,
                'cloudfront' => $cloudfront,
                's3' => $s3,
                'componentInfo' => array_merge($info, []),
                'iamUserPolicy' => PolicyHelper::iamUserPolicy(),
                'bucketPolicy' => PolicyHelper::bucketPolicy(
                    array_key_exists('functions', $lambda) ? array_map(function ($func) {
                        return $func['role'];
                    }, $lambda['functions']) : []
                ),
                'lambdaRolePolicy' => PolicyHelper::lambdaAssumeRolePolicy()
            ]);
        } else {
            return Craft::$app->getView()->renderTemplate('flux/_utility_not_configured');
        }
    }

    private static function lambdaStatus(): array
    {
        $status = Flux::getInstance()->lambda->getFunctionStatuses();
        $installed = $status['viewerRequest'] && $status['originResponse'];

        if ($installed) {
            $version = $status['viewerRequest']['version'] == $status['originResponse']['version'] ? $status['viewerRequest']['version'] : "Mismatch";

            return [
                'installed' => true,
                'version' => $version,
                'updateAvailable' => Flux::getInstance()->version != $version,
                'lastModified' => $status['viewerRequest']['lastModified'],
                'functions' => $status
            ];
        } else {
            return [
                'installed' => false
            ];
        }
    }

    private static function lambdaRoles($status): array
    {
        $roles = [];

        if (key_exists('functions', $status)) {
            $roles = array_map(function ($func) {
                return $func['role'];
            }, $status['functions']);
        }

        return array_values($roles);
    }
}