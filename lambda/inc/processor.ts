import { GetObjectCommand, PutObjectCommand, S3Client } from "@aws-sdk/client-s3";
import axios from "axios";
import sharp, {FitEnum} from "sharp";
import type { Readable } from 'stream';

import {compilePath, removeRootPrefix, TransformMode, TransformRequest} from "./parser";
import {FluxConfig, FluxSourceType} from "./config";
import { log } from "./logging";

const S3 = new S3Client({});

//  Either file exists in S3 (perhaps because it is cached) or it needs
//  to be fetched from an external origin. Either way this function needs
//  to return a buffer containing something to transform
export function fetchSource(request: TransformRequest, config: FluxConfig): Promise<Buffer> {
  let fileName = `${request.fileName}.${request.extension}`;
  if (request.sourceFilename) {
    fileName = request.sourceFilename;
  }

  // Read original file from S3
  if (request.source.type === FluxSourceType.LOCAL) {
    return new Promise((resolve, reject) => {
      const sourceFile = compilePath(request.source.subFolder || "", request.sourcePath, fileName);

      readFile(sourceFile, config)
        .then(buffer => resolve(buffer))
        .catch(error => {
          reject(error)
        })
    });
  } else {
    const localCachedFile = compilePath(
      config.rootPrefix,
      request.prefix.replace(`/${request.transformPathSegment}`, ""),
      fileName
    );

    return new Promise((resolve, reject) => {
      readFile(localCachedFile, config)
        .then(buffer => resolve(buffer))
        .catch(error => {
          const originFile = new URL(compilePath(request.sourcePath, fileName), request.source.url);

          if (originFile) {
            fetchExternalSource(originFile, config)
              .then((buffer) => {
                if (config.cachedEnabled) {
                  writeFile(localCachedFile, buffer, config);
                }

                resolve(buffer);
              })
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
    responseType: "arraybuffer"
  }).then(r => r.data as Buffer);
}

export function transformSource(input: Buffer, request: TransformRequest): Promise<Buffer> {
  const transform = request.manipulations;

  let mode: keyof FitEnum = 'cover';
  switch (transform.mode) {
    case TransformMode.STRETCH:
      mode = 'fill';
      break;
    case TransformMode.FIT:
      mode = 'contain';
      break;
    case TransformMode.CROP:
      mode = 'cover';
      break;
  }

  const sharpPositions: Record<string, string> = {
    "top-left": "left top",
    "top-center": "top",
    "top-right": "right top",
    "center-left": "left",
    "center-center": "centre",
    "center-right": "right",
    "bottom-left": "left bottom",
    "bottom-center": "bottom",
    "bottom-right": "right bottom"
  };

  const resizeParams: sharp.ResizeOptions = {
    fit: mode,
    position: Object.keys(sharpPositions).includes(transform.position) ? sharpPositions[transform.position] : 'center'
  }

  if (Number.isInteger(transform.width)) {
    resizeParams.width = transform.width as number;
  }

  if (Number.isInteger(transform.height)) {
    resizeParams.height = transform.height as number;
  }

  let process = sharp(input).resize(resizeParams);

  if (request.extension === "webp") {
    process = process.webp({
      quality: transform.quality
    });
  } else if (request.extension === "png") {
    process = process.png();
  } else {
    process = process.jpeg({
      quality: transform.quality
    });
  }

  return process.toBuffer();
}

export function readFile(key: string, config: FluxConfig): Promise<Buffer> {
  return new Promise<Buffer>((resolve, reject) => {
    log(config, "Reading file from S3", key);

    S3.send(
      new GetObjectCommand({
        Bucket: config.bucket,
        Key: key
      })
    )
      .then(response => {
      const stream = response.Body as Readable;

      const chunks: Buffer[] = []
      stream.on('data', chunk => chunks.push(chunk))
      stream.once('end', () => resolve(Buffer.concat(chunks)))
      stream.once('error', reject);
    })
      .catch(err => {
        log(config, "Unable to read file from S3", err);
        reject(err);
      })
  });
}

export function writeFile(key: string, buffer: Buffer, config: FluxConfig): Promise<Buffer> {
  return new Promise((resolve, reject) => {
    const extension = key.split('.').pop();
    const contentType = `image/${extension}`;

    log(config, "Writing file to S3", key);

    S3.send(
      new PutObjectCommand({
        Body: buffer,
        Bucket: config.bucket,
        ContentType: contentType,
        CacheControl: 'max-age=31536000',
        Key: key,
        StorageClass: 'STANDARD'
      })
    ).then(() => {
      resolve(buffer);
    }).catch(err => {
      log(config, "Exception while writing file to bucket", err);
      reject(err);
    });
  });
}