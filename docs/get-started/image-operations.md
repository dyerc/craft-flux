# Image Operations

Use the `craft.flux.transform()` function directly to additionally process the output image with effects and manipulation. The second parameter should be a standard Craft transform object. The third parameter is one of the operations on this page.

```twig
{{ craft.flux.transform(
  asset,
  { mode: 'crop', width: 1920 },
  {
    blur: 25,
    tint: { r: 255, g: 0, b: 0 }
  }
) }}
```

## Blur

_Allowed values:_ `true` or `0.3 - 1000`

When used with a boolean `true`, performs a fast 3x3 box blur (equivalent to a box linear filter).

```twig
{ blur: true }
```

Or provide a sigma value to perform a slower, more accurate Gaussian blur:

```twig
{ blur: 50 }
```

The sigma value should be a value between 0.3 and 1000 representing the sigma of the Gaussian mask, where `sigma = 1 + radius / 2`.

## Brightness

_Allowed values:_ `[float]`

Increase brightness by a multiplier. For instance to increase brightness by a factor of 2:

```twig
{ brightness: 2 }
```

## Greyscale

_Allowed values:_ `true`

Convert to 8-bit greyscale; 256 shades of grey. The output image will be web-friendly sRGB and contain three (identical) colour channels.

```twig
{ greyscale: true }
```

## Hue

_Allowed values:_ `[float]`

Degrees of hue rotation. For instance to hue rotate by 90 degrees:

```twig
{ hue: 90 }
```

## Lightness

_Allowed values:_ `[float]`

Manipulate lightness. For instance to increase lightness by +50:

```twig
{ lightness: 50 }
```

## Saturation

_Allowed values:_ `[float]`

Saturation multiplier.

```twig
{ saturation: 2 }
```

## Tint

_Allowed values:_ `{ r: 0-255, g: 0-255, b: 0-255 }` or hex code `#FFFFFF`

Tint the image using the provided colour.

```twig
{ tint: { r: 255, g: 100, b: 25 } }
```

Or provide a hex colour:

```twig
{ tint: "#FF6419" }
```
