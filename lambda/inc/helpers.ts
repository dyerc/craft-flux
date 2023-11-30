export type URLParams = { [p: string]: any };

// https://stackoverflow.com/questions/175739/how-can-i-check-if-a-string-is-a-valid-number
export function isNumeric(str: any) {
  if (typeof str != "string") return false; // we only process strings!

  return (
    // @ts-ignore
    !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
    !isNaN(parseFloat(str))
  ); // ...and ensure strings of whitespace fail
}

// Replication of matching function in Craft src/helpers/Image.php
export function calculateMissingDimension(
  targetWidth: number | undefined,
  targetHeight: number | undefined,
  sourceWidth: number,
  sourceHeight: number
) {
  if (targetWidth && targetHeight) {
    return [targetWidth, targetHeight];
  }

  return [
    targetWidth
      ? targetWidth
      : Math.round(
          (targetHeight || sourceHeight) * (sourceWidth / sourceHeight)
        ),
    targetHeight
      ? targetHeight
      : Math.round((targetWidth || sourceWidth) * (sourceHeight / sourceWidth)),
  ];
}
