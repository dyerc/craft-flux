<?php
/**
 * @copyright Copyright (c) Chris Dyer
 */

namespace dyerc\flux\helpers;

use Craft;
use craft\helpers\App;
use dyerc\flux\Flux;
use dyerc\flux\models\SettingsModel;

class PolicyHelper
{
    public static function iamUserPolicy(string|null $bucket = null, string|null $rootPrefix = null): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        if (!$bucket) {
            $bucket = App::parseEnv($settings->awsBucket);
        }

        if (!$rootPrefix) {
            $rootPrefix = App::parseEnv($settings->rootPrefix);
        }

        return [
            "Version" => "2012-10-17",
            "Statement" => [
                [
                    "Effect" => "Allow",
                    "Action" => "iam:PassRole",
                    "Resource" => "*",
                    "Condition" => [
                        "StringEquals" => [
                            "iam:PassedToService" => "lambda.amazonaws.com"
                        ]
                    ]
                ],
                // List all buckets
                [
                    "Effect" => "Allow",
                    "Action" => [
                        "s3:ListAllMyBuckets"
                    ],
                    "Resource" => "*"
                ],
                // S3 operations on entire bucket
                [
                    "Effect" => "Allow",
                    "Action" => [
                        "s3:PutBucketPolicy",
                        "s3:List*",
                    ],
                    "Resource" => "arn:aws:s3:::$bucket"
                ],
                // S3 operations on bucket scope in use
                [
                    "Effect" => "Allow",
                    "Action" => [
                        "s3:Get*",
                        "s3:Put*",
                        "s3:DeleteObject",
                        "s3-object-lambda:Get*",
                        "s3-object-lambda:List*"
                    ],
                    "Resource" => empty($rootPrefix) ? "arn:aws:s3:::$bucket/*" : "arn:aws:s3:::$bucket/$rootPrefix/*"
                ],
                // CloudFront & Lambda
                [
                    "Effect" => "Allow",
                    "Action" => [
                        "cloudfront:*",
                        "iam:GetPolicy",
                        "iam:GetPolicyVersion",
                        "iam:GetRole",
                        "iam:GetRolePolicy",
                        "iam:PutRolePolicy",
                        "iam:CreateRole",
                        "iam:ListAttachedRolePolicies",
                        "iam:ListRolePolicies",
                        "iam:ListRoles",
                        "lambda:*",
                        "logs:DescribeLogGroups"
                    ],
                    "Resource" => "*"
                ],
                [
                    "Effect" => "Allow",
                    "Action" => [
                        "logs:DescribeLogStreams",
                        "logs:GetLogEvents",
                        "logs:FilterLogEvents"
                    ],
                    "Resource" => "arn:aws:logs:*:*:log-group:/aws/lambda/*"
                ]
            ]
        ];
    }

    public static function bucketPolicy(array $functionArns): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();
        $bucket = App::parseEnv($settings->awsBucket);
        $rootPrefix = App::parseEnv($settings->rootPrefix);

        // If we are using S3, grant access to the entire bucket
        if (self::getHasS3Filesystems()) {
            $rootPrefix = "";
        }

        $actions = ["s3:GetObject", "s3:PutObject"];

        $policy = [
            "Version" => "2012-10-17",
            "Statement" => [
                [
                    "Effect" => "Allow",
                    "Principal" => "*",
                    "Action" => "s3:GetObject",
                    "Resource" => empty($rootPrefix) ? "arn:aws:s3:::$bucket/*" : "arn:aws:s3:::$bucket/$rootPrefix/*"
                ]
            ]
        ];

        foreach ($functionArns as $arn) {
            $policy['Statement'][] = [
                "Effect" => "Allow",
                "Principal" => [
                    "AWS" => $arn
                ],
                "Action" => $actions,
                "Resource" => empty($rootPrefix) ? "arn:aws:s3:::$bucket/*" : "arn:aws:s3:::$bucket/$rootPrefix/*"
            ];
        }

        return $policy;
    }

    public static function lambdaAssumeRolePolicy(): array
    {
        return [
            'Version' => '2012-10-17',
            'Statement' => [
                'Effect' => 'Allow',
                'Principal' => [
                    'Service' => [
                        'edgelambda.amazonaws.com',
                        'lambda.amazonaws.com'
                    ]
                ],
                'Action' => 'sts:AssumeRole',
            ]
        ];
    }

    public static function lambdaRolePolicy(): array
    {
        /* @var SettingsModel */
        $settings = Flux::getInstance()->getSettings();

        $policy = [
            'Version' => '2012-10-17',
            'Statement' => []
        ];

        if ($settings->loggingEnabled) {
            $policy['Statement'][] = [
                'Effect' => 'Allow',
                'Action' => [
                    "logs:CreateLogGroup",
                    "logs:CreateLogStream",
                    "logs:PutLogEvents"
                ],
                'Resource' => [
                    "arn:aws:logs:*:*:*"
                ]
            ];
        }

        return $policy;
    }

    public static function prettyPrint(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }

    private static function getHasS3Filesystems(): bool
    {
        $filesystems = Craft::$app->fs->getAllFilesystems();

        foreach ($filesystems as $fs) {
            if (is_a($fs, "craft\\awss3\\Fs")) {
                return true;
            }
        }

        return false;
    }
}