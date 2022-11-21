<img src="./resources/icon.svg" width="100" height="100"><br>

[![Stable Version](https://img.shields.io/packagist/v/dyerc/craft-flux?label=stable)](https://packagist.org/packages/dyerc/craft-flux) [![Total Downloads](https://img.shields.io/packagist/dt/dyerc/craft-flux)](https://packagist.org/packages/dyerc/craft-flux)

# Flux Plugin for Craft CMS


## Features
- Efficient on demand image processing when an image version is requested
- Automatically serve WebP to browsers that support it without having to explicitly define WebP transforms in your templates
- Serve files from a local filesystem volume (with caching) or ones that are already in an S3 filesystem
- Compatible with your existing Craft template code and named transforms. Simply enable Flux and your templates don't need to change
- Transforms are completely URL based, no database queries are needed