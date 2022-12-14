# Transform Parameters

Flux aims to be a drop-in replacement for Craft's own built in image transforms. You can transform your assets with Flux in any of the following ways:

Directly within a template:

```twig
<img src="{{ asset.getUrl({
  mode: 'crop',
  width: 100,
  height: 100,
  quality: 75,
}) }}">
```

Via named transforms:

```twig
<img src="{{ asset.getUrl('transform') }}">
```

Or using `setTransform()`:

```twig
{% do asset.setTransform({ mode: 'crop', width: 100 }) %}
<img src="{{ asset.url }}"
     width="{{ asset.width }}"
     height="{{ asset.height }}"
>
```

## Defining Transforms 

### width [int]
Width of the image, in pixels. Leave as `null` to auto-scale the width to match the height.

### height [int]
Height of the image, in pixels. Leave as `null` to auto-scale the height to match the width.

### mode [string]
*Default:* `'crop'`<br>
*Allowed values:* `'crop'`, `'fit'`, `'stretch'`

- **Crop** – Crops the image to the specified width and height, scaling the image up if necessary. (This is the default mode.)
- **Fit** – Scales the image so that it is as big as possible with all dimensions fitting within the specified width and height.
- **Stretch** – Stretches the image to the specified width and height.

### format [string]
*Allowed values:* `'jpg'`, `'png'`, `'webp'`

The transform's resulting image format.

### position [string]
*Default:* `'center-center'`<br>
*Allowed values:* `'top-left'`, `'top-center'`, `'top-right'`, `'center-left'`, `'center-center'`, `'center-right'`, `'bottom-left'`, `'bottom-center'`, `'bottom-right'`

If **mode** is set to `'crop'`, this is the image area that Flux will center the crop on.

### quality [int]
*Default:* the value defined in the plugin settings<br>
*Allowed values:* `1 - 100`