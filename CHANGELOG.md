# Flux Release Notes

## 5.1.1 - 2026-01-07

- Fix a critical issue with edge functions loading on Node.js 24

## 5.1.0 - 2026-01-05

> [!NOTE]  
> Future installation will now create/update Lambda functions to run on Node.js 24. Previously Node.js 20 was used which will stop receiving security patches from April 30, 2026.

- Update runtime and Lambda installation for Node.js 24
- Upgrade sharp to 0.34.5

## 5.0.5 - 2025-08-18

- Fix support for disabled upscaling of images in `crop` and `fit` modes

## 5.0.4 - 2025-06-03

- Enable overriding default AWS cache policy and origin request policy naming through `config/flux.php`

## 5.0.3 - 2024-10-08

- Parse environment variables in S3 bucket filesystem settings sent to Lambda
- Fix typo in settings JSON object sent to Lambda

## 5.0.2 - 2024-09-30

- Wait for Lambda to process changes after deploying a new function version

## 5.0.1 - 2024-09-13

- Fix issue checking function status whilst deploying Lambda update

## 5.0.0 - 2024-05-29

- Update wording within setup wizard to mention changing origin access setting for non-public S3 buckets
- Fix issue with AWS validating architecture parameter
- Add compatibility with Craft 5
- Use Node.js 20.x for Lambda functions
- Upgrade sharp to 0.32.6
