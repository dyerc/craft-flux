# How Does it Work?

Flux integrates AWS with your site and all image transformations will be processed and served by CloudFront:

![Flux diagram](../resources/how_it_works.svg)

### 1. Image requests are routed to CloudFront

When your site serves a transformed image, Flux will intercept this and construct a query string pointing to your CloudFront distribution. This could look something like: 

```
https://distro.cloudfront.net/assetVolume/image.jpg?mode=fit&w=1920&v=abcdefghijklmnopqrstuvwxyz
```

Optionally a verification token is appended so that only transforms your templates request are available. A malicious user can't construct a transform of their own.

### 2. Flux routes this request to a cached transformed file

One of two Lambda functions that Flux installs will route this request to a unique file representing this version of your original image.

### 3. Image is served or transformed

If this image exists and has already been transformed by Flux, CloudFront will immediately serve it. Alternatively, another Lambda function will use [Sharp](https://sharp.pixelplumbing.com/) to convert and process the image. The original image will be fetched from your Craft site, or from S3 if your site uses an S3 filesystem already.

Flux aims to support exactly the same [transform options](https://craftcms.com/docs/4.x/image-transforms.html) as Craft does itself. This includes `crop`, `fit` and `stretch` modes, as well as focal points, quality and format. 