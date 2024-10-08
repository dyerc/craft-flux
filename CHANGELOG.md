# Flux Release Notes

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
