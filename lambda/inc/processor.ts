import {
  GetObjectCommand,
  PutObjectCommand,
  S3Client,
} from "@aws-sdk/client-s3";
import axios from "axios";
import sharp, { FitEnum } from "sharp";
import type { Readable } from "stream";

import {
  compilePath,
  FocalPoint,
  TransformMode,
  TransformRequest,
} from "./parser";
import { FluxConfig, FluxSourceType } from "./config";
import { log } from "./logging";
import { calculateMissingDimension } from "./helpers";

const S3 = new S3Client({});

//  Either file exists in S3 (perhaps because it is cached) or it needs
//  to be fetched from an external origin. Either way this function needs
//  to return a buffer containing something to transform
export function fetchSource(
  request: TransformRequest,
  config: FluxConfig
): Promise<Buffer> {
  let fileName = `${request.fileName}.${request.extension}`;
  if (request.sourceFilename) {
    fileName = request.sourceFilename;
  }

  // Read original file from S3
  if (request.source.type === FluxSourceType.LOCAL) {
    return new Promise((resolve, reject) => {
      const sourceFile = compilePath(
        request.source.subFolder || "",
        request.sourcePath,
        fileName
      );

      readFile(sourceFile, config)
        .then((buffer) => resolve(buffer))
        .catch((error) => {
          reject(error);
        });
    });
  } else {
    const localCachedFile = compilePath(
      config.rootPrefix,
      request.prefix.replace(`/${request.transformPathSegment}`, ""),
      fileName
    );

    return new Promise((resolve, reject) => {
      readFile(localCachedFile, config)
        .then((buffer) => resolve(buffer))
        .catch((error) => {
          const originFile = new URL(
            compilePath(request.sourcePath, fileName),
            request.source.url
          );

          if (originFile) {
            fetchExternalSource(originFile, config).then((buffer) => {
              if (config.cachedEnabled) {
                writeFile(localCachedFile, buffer, config);
              }

              resolve(buffer);
            });
          } else {
            reject(error);
          }
        });
    });
  }
}

export function fetchExternalSource(sourceUrl: URL, config: FluxConfig) {
  log(config, "Fetching external source", sourceUrl.href);

  return axios({
    url: sourceUrl.href,
    responseType: "arraybuffer",
  }).then((r) => r.data as Buffer);
}

export function scaleToFit(
  proc: sharp.Sharp,
  width: number,
  height: number,
  metadata: sharp.Metadata
): sharp.Sharp {
  return proc.resize({
    fit: "inside",
    width: width,
    height: height,
  });
}

export function stretchToFit(
  proc: sharp.Sharp,
  width: number,
  height: number,
  metadata: sharp.Metadata
): sharp.Sharp {
  return proc.resize({
    fit: "fill",
    width: width,
    height: height,
  });
}

export function scaleAndCrop(
  proc: sharp.Sharp,
  targetWidth: number,
  targetHeight: number,
  position: FocalPoint | string,
  metadata: sharp.Metadata
): sharp.Sharp {
  const sharpPositions: Record<string, string> = {
    "top-left": "left top",
    "top-center": "top",
    "top-right": "right top",
    "center-left": "left",
    "center-center": "centre",
    "center-right": "right",
    "bottom-left": "left bottom",
    "bottom-center": "bottom",
    "bottom-right": "right bottom",
  };

  // Maybe allow enabling/disabling upscaling if future?
  const scaleIfSmaller = true;

  if (!metadata.width || !metadata.height) {
    return proc;
  }

  let newWidth = metadata.width;
  let newHeight = metadata.height;

  // If upscaling is fine OR we have to downscale.
  if (
    scaleIfSmaller ||
    (metadata.width > targetWidth && metadata.height > targetHeight)
  ) {
    const factor = Math.min(
      metadata.width / targetWidth,
      metadata.height / targetHeight
    );
    newHeight = Math.round(metadata.height / factor);
    newWidth = Math.round(metadata.width / factor);

    proc = proc.resize(newWidth, newHeight);
  } else if (
    (targetWidth > metadata.width || targetHeight > metadata.height) &&
    !scaleIfSmaller
  ) {
    const factor = Math.max(
      targetWidth / metadata.width,
      targetHeight / metadata.height
    );
    targetHeight = Math.round(targetHeight / factor);
    targetWidth = Math.round(targetWidth / factor);
  }

  let x1 = 0,
    x2 = 0,
    y1 = 0,
    y2 = 0;

  if (typeof position !== "string") {
    const centerX = newWidth * position.x;
    const centerY = newHeight * position.y;

    x1 = Math.round(centerX - targetWidth / 2);
    y1 = Math.round(centerY - targetHeight / 2);
    x2 = x1 + targetWidth;
    y2 = y1 + targetHeight;

    // Move bounding box around to ensure it fits
    if (x1 < 0) {
      x2 -= x1;
      x1 = 0;
    }
    if (y1 < 0) {
      y2 -= y1;
      y1 = 0;
    }
    if (x2 > newWidth) {
      x1 -= x2 - newWidth;
      x2 = newWidth;
    }
    if (y2 > newHeight) {
      y1 -= y2 - newHeight;
      y2 = newHeight;
    }
  } else {
    const cropComponents = position.split("-");
    const verticalPosition = cropComponents[0];
    const horizontalPosition = cropComponents[1];

    // Now crop.
    if (newWidth - targetWidth > 0) {
      switch (horizontalPosition) {
        case "left":
          x1 = 0;
          x2 = x1 + targetWidth;
          break;
        case "right":
          x2 = newWidth;
          x1 = newWidth - targetWidth;
          break;
        default:
          x1 = Math.round((newWidth - targetWidth) / 2);
          x2 = x1 + targetWidth;
          break;
      }

      y1 = 0;
      y2 = y1 + targetHeight;
    } else if (newHeight - targetHeight > 0) {
      switch (verticalPosition) {
        case "top":
          y1 = 0;
          y2 = y1 + targetHeight;
          break;
        case "bottom":
          y2 = newHeight;
          y1 = newHeight - targetHeight;
          break;
        default:
          y1 = Math.round((newHeight - targetHeight) / 2);
          y2 = y1 + targetHeight;
          break;
      }

      x1 = 0;
      x2 = x1 + targetWidth;
    } else {
      x1 = Math.round((newWidth - targetWidth) / 2);
      x2 = x1 + targetWidth;
      y1 = Math.round((newHeight - targetHeight) / 2);
      y2 = y1 + targetHeight;
    }
  }

  return proc.extract({ left: x1, top: y1, width: x2 - x1, height: y2 - y1 });
}

export function transformSource(
  input: Buffer,
  request: TransformRequest
): Promise<Buffer> {
  const transform = request.manipulations;
  let proc = sharp(input);

  return proc.metadata().then((metadata) => {
    let width, height;

    if (Number.isInteger(transform.width)) {
      width = transform.width as number;
    }

    if (Number.isInteger(transform.height)) {
      height = transform.height as number;
    }

    const dimensions = calculateMissingDimension(
      width,
      height,
      metadata.width || 0,
      metadata.height || 0
    );

    width = dimensions[0];
    height = dimensions[1];

    if (transform.mode === TransformMode.FIT) {
      proc = scaleToFit(proc, width, height, metadata);
    } else if (transform.mode === TransformMode.STRETCH) {
      proc = stretchToFit(proc, width, height, metadata);
    } else {
      proc = scaleAndCrop(proc, width, height, transform.position, metadata);
    }

    if (request.extension === "webp") {
      proc = proc.webp({
        quality: transform.quality,
      });
    } else if (request.extension === "png") {
      proc = proc.png();
    } else {
      proc = proc.jpeg({
        quality: transform.quality,
      });
    }

    return proc.toBuffer();
  });
}

export function readFile(key: string, config: FluxConfig): Promise<Buffer> {
  return new Promise<Buffer>((resolve, reject) => {
    log(config, "Reading file from S3", key);

    S3.send(
      new GetObjectCommand({
        Bucket: config.bucket,
        Key: key,
      })
    )
      .then((response) => {
        const stream = response.Body as Readable;

        const chunks: Buffer[] = [];
        stream.on("data", (chunk) => chunks.push(chunk));
        stream.once("end", () => resolve(Buffer.concat(chunks)));
        stream.once("error", reject);
      })
      .catch((err) => {
        log(config, "Unable to read file from S3", err);
        reject(err);
      });
  });
}

export function writeFile(
  key: string,
  buffer: Buffer,
  config: FluxConfig
): Promise<Buffer> {
  return new Promise((resolve, reject) => {
    const extension = key.split(".").pop();
    const contentType = `image/${extension}`;

    log(config, "Writing file to S3", key);

    S3.send(
      new PutObjectCommand({
        Body: buffer,
        Bucket: config.bucket,
        ContentType: contentType,
        CacheControl: "max-age=31536000",
        Key: key,
        StorageClass: "STANDARD",
      })
    )
      .then(() => {
        resolve(buffer);
      })
      .catch((err) => {
        log(config, "Exception while writing file to bucket", err);
        reject(err);
      });
  });
}
