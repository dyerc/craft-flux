<?php

/**
 * Flux config.php
 *
 * This file exists only as a template for the Flux settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'flux.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    '*' => [
        // Intercept image transforms and route them through CloudFront
        // 'enabled' => true,

        // Ensure requests originated from Craft by appending an HMAC verification token
        // 'verifyQuery' => true,

        // HMAC secret used if `verifyQuery` is enabled. If blank, a secret will be automatically generated when Flux is first installed
        // 'verifySecret' => '',

        // For local asset file systems, store a duplicate in S3 to speed up processing asset variations
        // 'cacheEnabled' => true,

        // When an asset is changed, automatically submit a CloudFront invalidation and purge any transformed files
        // 'autoPurgeAssets' => true,

        /*
         * AWS Settings
        */

        // AWS Access Key ID
        // 'awsAccessKeyId' => '',

        // AWS Secret Access Key
        // 'awsSecretAccessKey' => '',

        // Prefix for auto generated AWS resources
        // 'awsResourcePrefix' => 'Flux',

        // S3 Bucket name
        // 'awsBucket' => '',

        // CloudFront distribution ID
        // 'cloudFrontDistributionId' => '',

        // CloudFront domain
        // 'cloudFrontDomain' => '',

        // S3 bucket region
        // 'awsRegion' => '',

        // S3 bucket root prefix
        // 'rootPrefix' => "Flux",

        /*
         * Advanced Settings
        */

        // Automatically serve WebP files if the users browser supports it via the Accept header
        // 'acceptWebp' => true,

        // Default JPG transform quality unless specified
        // 'jpegQuality' => 80,

        // Default WebP transform quality unless specified
        // 'webpQuality' => 80,

        // Log more detailed information to CloudWatch
        // 'loggingEnabled' => true,

        // Maximum memory origin response function can use
        // 'lambdaMemory' => 512,

        // Maximum time origin response function can run
        // 'lambdaTimeout' => 15,
    ]
];