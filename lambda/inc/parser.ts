import { createHmac } from "crypto";

import { ParsedUrlQuery } from "querystring";
import { FluxConfig, FluxSource } from "./config";
import { CloudFrontRequest } from "aws-lambda";
import { log } from "./logging";
import { isNumeric } from "./helpers";

const WEBP_EXT = "webp";

export enum TransformMode {
  FIT = "fit",
  CROP = "crop",
  STRETCH = "stretch",
}

export const ImageFormats = ["jpg", "png", "webp"];

export const CropPositions = [
  "top-left",
  "top-center",
  "top-right",
  "center-left",
  "center-center",
  "center-right",
  "bottom-left",
  "bottom-center",
  "bottom-right",
];

export interface TransformRequest {
  prefix: string;
  fileName: string;
  extension: string;
  source: FluxSource;
  sourcePath: string;
  sourceFilename?: string;
  transformPathSegment?: string;

  manipulations: Manipulations;
}

export interface FocalPoint {
  x: number;
  y: number;
}

export interface Manipulations {
  mode: TransformMode;
  width: number | string;
  height: number | string;
  position: string | FocalPoint;
  quality?: number;
}

export function coerceInt(
  input: string,
  fallback: string | number
): string | number {
  if (Number.isInteger(parseInt(input))) {
    return parseInt(input);
  } else {
    return fallback;
  }
}

export function requestAccepts(request: CloudFrontRequest) {
  return request.headers["accept"] ? request.headers["accept"][0].value : "";
}

export function parseTransformPathSegment(uri: string): string | undefined {
  const matches = uri.match(
    /_(\d+|AUTO)x(\d+|AUTO)_(fit|crop|stretch)_([a-z\d\\.]+-[a-z\d\\.]+)_?(\d+)?/
  );
  return matches ? matches[0] : undefined;
}

export function validHmacToken(
  request: CloudFrontRequest,
  params: ParsedUrlQuery,
  config: FluxConfig
): boolean {
  if (!params.v) {
    return false;
  }

  const secret = config.verifySecret;
  let path = request.uri.substring(1); // Remove starting forward slash
  path = removeRootPrefix(path, config);

  const parts = request.querystring.split("&v=");

  if (Object.keys(params).length > 1 && parts.length === 2) {
    const signer = createHmac("sha256", secret);
    const hmac = signer.update(`${path}?${parts[0]}`).digest("hex");

    return hmac === parts[1];
  } else {
    return false;
  }
}

export function parseRequest(
  request: CloudFrontRequest,
  params: ParsedUrlQuery,
  config: FluxConfig
): TransformRequest | null {
  const uri = request.uri;
  const accepts = requestAccepts(request);
  const match = uri.match(/(.*)\/(.*)\.(.*)$/);

  if (!match || match.length < 3) {
    return null;
  }

  let prefix = match[1];
  if (prefix.charAt(0) === "/") {
    prefix = prefix.substring(1);
  }

  const fileName = match[2];
  const extension = match[3];
  let sourceFilename = undefined;
  let outputExtension = extension;

  // Parse source

  if (request.headers["x-flux-source-filename"]) {
    sourceFilename = request.headers["x-flux-source-filename"][0].value;
  }

  const transformPathSegment = parseTransformPathSegment(prefix);
  const sourcePrefix = removeRootPrefix(
    prefix.replace(`/${transformPathSegment}`, ""),
    config
  );
  const sourceComponents = sourcePrefix.split("/").filter((s) => s);
  const source: FluxSource | undefined = config.sources.find((s) => {
    let root = sourceComponents[0];
    if (root === config.rootPrefix) {
      root = sourceComponents[1];
    }
    return s.handle === root;
  });

  if (!source) {
    log(config, "Unable to parse source", sourcePrefix);
    log(
      config,
      "Available sources",
      config.sources.map((s) => s.handle)
    );
    return null;
  }

  const sourcePath = compilePath(...sourceComponents.slice(1));

  // Determine output image format
  if (params.f && ImageFormats.includes(params.f as string)) {
    outputExtension = params.f as string;
  } else {
    // Automatically upgrade to WebP if enabled and supported
    if (config.acceptWebp && accepts.includes(WEBP_EXT)) {
      outputExtension = WEBP_EXT;
    }
  }

  // Ensure we have a source if the output format has changed
  if (!sourceFilename && extension !== outputExtension) {
    sourceFilename = `${fileName}.${extension}`;
  }

  const manipulations = parseManipulations(params, outputExtension, config);
  if (manipulations) {
    return {
      prefix,
      fileName,
      extension: outputExtension,
      manipulations,
      source,
      sourcePath,
      sourceFilename,
      transformPathSegment,
    };
  } else {
    return null;
  }
}

export function parseManipulations(
  params: ParsedUrlQuery,
  extension: string,
  config: FluxConfig
): Manipulations | null {
  // ?mode=... must be set as well as dimension, otherwise forward on request unmodified
  if (!params.mode || (!params.w && !params.h)) {
    log(config, "Request must contain a mode and a width or height");
    return null;
  }

  const transform: Manipulations = {
    mode: (Object.values(TransformMode) as string[]).includes(
      params.mode as string
    )
      ? (params.mode as TransformMode)
      : TransformMode.FIT,
    width: "AUTO",
    height: "AUTO",
    position: "center-center",
  };

  transform.width = coerceInt(params.w as string, transform.width);
  transform.height = coerceInt(params.h as string, transform.height);

  if (params.pos) {
    if (CropPositions.includes(params.pos as string)) {
      transform.position = params.pos as string;
    } else if (!Array.isArray(params.pos) && params.pos.indexOf("-") !== -1) {
      // Check if pos param is a number-number format eg. 0.9987-0.4365
      const coords = params.pos.split("-");

      // If all coords are valid numbers, round to 3dp and assign to position
      if (coords.length === 2 && coords.every((x) => isNumeric(x))) {
        transform.position = {
          x: parseFloat(coords[0]),
          y: parseFloat(coords[1]),
        };
      }
    }
  }

  if (params.q) {
    const q = coerceInt(params.q as string, 0);
    if (q > 0) {
      transform.quality = q as number;
    }
  } else {
    if (extension == "jpg") {
      transform.quality = config.jpegQuality;
    } else if (extension == "webp") {
      transform.quality = config.webpQuality;
    }
  }

  return transform;
}

export function transformPath(request: TransformRequest): string {
  const m = request.manipulations;

  let transform = `_${m.width}x${m.height}_${m.mode}_`;

  if (typeof m.position === "string") {
    transform += m.position;
  } else {
    transform += `${m.position.x.toFixed(3)}-${m.position.y.toFixed(3)}`;
  }

  if (m.quality) {
    transform += `_${m.quality}`;
  }

  return compilePath(
    request.prefix,
    transform,
    `${request.fileName}.${request.extension}`
  );
}
/*
export function parsePath(uri: string): Manipulations|null {
  const matches = uri.match(/_(\d+|AUTO)x(\d+|AUTO)_(fit|crop|stretch)_([a-z]+-[a-z]+)_(\d+)/);

  if (matches && matches.length === 5) {
    return {
      width: coerceInt(matches[0], "AUTO"),
      height: coerceInt(matches[1], "AUTO"),
      mode: matches[2] as TransformMode,
      position: matches[3],
      quality: parseInt(matches[4])
    }
  } else {
    return null;
  }
}
*/
export function compilePath(...elements: string[]): string {
  return elements.filter((e) => e && e.length > 0).join("/");
}

export function removeRootPrefix(input: string, config: FluxConfig): string {
  if (input.startsWith(`/${config.rootPrefix}`)) {
    return input.slice(config.rootPrefix.length + 1);
  } else if (input.startsWith(`${config.rootPrefix}/`)) {
    return input.slice(config.rootPrefix.length + 1);
  } else {
    return input;
  }
}
