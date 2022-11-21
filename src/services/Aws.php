<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\services;

use Craft;
use Aws\Iam\IamClient;
use craft\helpers\App;
use dyerc\flux\Flux;
use dyerc\flux\helpers\PolicyHelper;
use dyerc\flux\models\SettingsModel;
use yii\base\Component;

class Aws extends Component
{
    /* @var string */
    public const ROLE_SUFFIX = "-Role";

    /* @var string */
    public const ROLE_INLINE_POLICY_SUFFIX = "-Policy";

    public function iamClient()
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        return new IamClient([
            'version' => 'latest',
            'region' => 'us-east-1', // Lambda@Edge must always be in us-east-1
            'credentials' => [
                'key' => App::parseEnv($settings->awsAccessKeyId),
                'secret' => App::parseEnv($settings->awsSecretAccessKey)
            ]
        ]);
    }

    public function updateOrCreateLambdaRole(string $name): string
    {
        $inlinePolicy = json_encode(PolicyHelper::lambdaRolePolicy());

        try {
            $existing = $this->iamClient()->getRole([
                'RoleName' => $name
            ]);

            $roleArn = $existing['Role']['Arn'];
        } catch (\Exception $e) {
            $response = $this->iamClient()->createRole([
                'RoleName' => $name,
                'AssumeRolePolicyDocument' => json_encode(PolicyHelper::lambdaAssumeRolePolicy())
            ]);

            // Allow everything to settle before proceeding
            // https://stackoverflow.com/questions/37503075/invalidparametervalueexception-the-role-defined-for-the-function-cannot-be-assu
            sleep(10);

            $roleArn = $response['Role']['Arn'];
        }

        // Check or update the inline role policy
        $rolePolicyParams = [
            'RoleName' => $name,
            'PolicyName' => $name . self::ROLE_INLINE_POLICY_SUFFIX
        ];

        try {
            $policy = $this->iamClient()->getRolePolicy($rolePolicyParams);

            if ($policy['PolicyDocument'] == $inlinePolicy) {
                return $roleArn;
            }
        } catch (\Exception $e) {
            // Most likely the policy doesn't exist
            Craft::info("Creating inline policy");
        }

        $this->iamClient()->putRolePolicy(array_merge($rolePolicyParams, [
            'PolicyDocument' => $inlinePolicy
        ]));

        return $roleArn;
    }
}