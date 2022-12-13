import { describe, expect, test } from '@jest/globals';
import { CloudFrontResultResponse } from "aws-lambda";
import {createCloudfrontContext, createCloudFrontResponseEvent, createOriginServer} from "./utils";
import * as crypto from "crypto";
import { DefaultConfig, FluxConfig } from "../inc/config";
import { mockClient } from 'aws-sdk-client-mock';
import 'aws-sdk-client-mock-jest';
import AWS from "aws-sdk";
import {GetObjectCommand, PutObjectCommand, S3Client} from "@aws-sdk/client-s3";
import * as fs from "fs";
import path from "path";
const FileType = require('file-type');

AWS.config.update({ region: 'us-east-1' });

const handler = require('../response.ts').handler;

const sampleConfig: FluxConfig = Object.assign({}, DefaultConfig, {
  loggingEnabled: false,
  sources: [{
    type: "remote",
    handle: "testAssets",
    url: "http://localhost:4000/uploads/"
  }]
})

async function handle(url: string, options = {}, config = {}): Promise<CloudFrontResultResponse> {
  const context = createCloudfrontContext();

  let parts = url.split("?");
  const event = createCloudFrontResponseEvent(Object.assign({}, {
    uri: parts[0],
    querystring: parts[1]
  }, options));

  return new Promise(resolve => {
    // @ts-ignore
    global.fluxConfig = Object.assign({}, sampleConfig, config);
    handler(event, context, (error: null, result: CloudFrontResultResponse) => {
      resolve(result);
    })
  })
}

describe('response', () => {
  test('forwards on 200 response', async () => {
    const result = await handle("/testAssets/_1920x1080_fit_center-center_80/image.jpg?mode=fit&w=1920&h=1080", { status: "200" }, { verifyQuery: false });

    expect(result.status).toEqual("200");
    expect(result.body).toBeUndefined();
  });

  test('processes 403 response from origin and stores cached copy', async () => {
    const s = createOriginServer({
      '/uploads/data/folder/image.jpg': 'image.jpg'
    });

    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).rejects();
    mockS3Client.on(PutObjectCommand).resolves({});
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/data/folder/_500xAUTO_fit_center-center_80/image.jpg?mode=fit&w=500", { status: "403" }, { verifyQuery: false });

    expect(result.status).toEqual("200");
    expect(result.body?.length).toBeLessThan(50000);
    // fs.writeFileSync("test.jpg", result.body, 'base64');
    s.close();

    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(GetObjectCommand, {
      Key: "Flux/testAssets/data/folder/image.jpg"
    });
    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(PutObjectCommand, {
      Key: "Flux/testAssets/data/folder/image.jpg"
    });
    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(PutObjectCommand, {
      Key: "Flux/testAssets/data/folder/_500xAUTO_fit_center-center_80/image.jpg"
    });
  });

  test('uses source filename header if provided', async () => {
    const s = createOriginServer({
      '/uploads/data/folder/image.jpg': 'image.jpg'
    });

    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).rejects();
    mockS3Client.on(PutObjectCommand).resolves({});
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/data/folder/_500xAUTO_fit_center-center_80/image.webp?mode=fit&w=500", {
      status: "403",
      requestHeaders: {
        'x-flux-source-filename': [{
          key: "X-Flux-Source-Filename",
          value: "image.jpg"
        }]
      }
    }, { verifyQuery: false });

    expect(result.status).toEqual("200");
    expect(result.body?.length).toBeLessThan(50000);
    // fs.writeFileSync("test.jpg", result.body, 'base64');
    s.close();

    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(GetObjectCommand, {
      Key: "Flux/testAssets/data/folder/image.jpg"
    });
    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(PutObjectCommand, {
      Key: "Flux/testAssets/data/folder/image.jpg"
    });
    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(PutObjectCommand, {
      Key: "Flux/testAssets/data/folder/_500xAUTO_fit_center-center_80/image.webp"
    });
  });

  test('uses cached file from source filename header', async () => {
    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).resolves({
      // @ts-ignore
      Body: fs.createReadStream(path.join(__dirname, "fixtures", "image.jpg"))
    });
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/data/folder/_500xAUTO_fit_center-center_80/image.webp?mode=fit&w=500", {
      status: "403",
      requestHeaders: {
        'x-flux-source-filename': [{
          key: "X-Flux-Source-Filename",
          value: "image.jpg"
        }]
      }
    }, { verifyQuery: false });

    expect(result.status).toEqual("200");
    expect(result.body?.length).toBeLessThan(50000);

    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(GetObjectCommand, {
      Key: "Flux/testAssets/data/folder/image.jpg"
    });
    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(PutObjectCommand, {
      Key: "Flux/testAssets/data/folder/_500xAUTO_fit_center-center_80/image.webp"
    });
  });

  test('processes cached file without hitting origin', async () => {
    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).resolves({
      // @ts-ignore
      Body: fs.createReadStream(path.join(__dirname, "fixtures", "image.jpg"))
    });
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/data/folder/_500x300_stretch_center-center_80/image.jpg?mode=stretch&w=500&h=300", { status: "403" }, { verifyQuery: false });

    expect(result.status).toEqual("200");
    expect(result.body?.length).toBeLessThan(50000);
  });

  test('returns a redirect if the converted file is over 1MB', async () => {
    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).resolves({
      // @ts-ignore
      Body: fs.createReadStream(path.join(__dirname, "fixtures", "image.jpg"))
    });
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/data/folder/_2000x2000_stretch_center-center_100/image.jpg?mode=stretch&w=2000&h=2000&q=100", { status: "403" }, { verifyQuery: false });

    expect(result.status).toEqual("302");
    // @ts-ignore
    expect(result.headers["Location"]).toEqual([{ key: "Location", value: "/testAssets/data/folder/_2000x2000_stretch_center-center_100/image.jpg" }]);
  });

  test('handles S3 source', async () => {
    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).resolves({
      // @ts-ignore
      Body: fs.createReadStream(path.join(__dirname, "fixtures", "image.jpg"))
    });
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/s3Assets/folder/_500xAUTO_stretch_center-center_100/image.jpg?mode=fit&w=500", { status: "403" }, {
      verifyQuery: false,
      sources: [{
        type: "local",
        handle: 's3Assets',
        region: DefaultConfig.region,
        bucket: DefaultConfig.bucket,
        subFolder: "data"
      }]
    });

    expect(result.status).toEqual("200");

    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(GetObjectCommand, {
      Key: "data/folder/image.jpg"
    });
    // @ts-ignore
    expect(mockS3Client).toHaveReceivedCommandWith(PutObjectCommand, {
      Key: "Flux/s3Assets/folder/_500xAUTO_stretch_center-center_100/image.jpg"
    });
  });

  test('responds with png image', async () => {
    const s = createOriginServer({
      '/uploads/image.jpg': 'image.jpg'
    });

    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).rejects();
    mockS3Client.on(PutObjectCommand).resolves({});
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/_600xAUTO_fit_center-center/image.png?mode=fit&w=600&f=png", {
      status: "403",
      requestHeaders: {
        'x-flux-source-filename': [{
          key: "X-Flux-Source-Filename",
          value: "image.jpg"
        }]
      }
    }, { verifyQuery: false });

    expect(result.status).toEqual("200");
    const t = await FileType.fromBuffer(Buffer.from(result.body as string, 'base64'));
    expect(t?.ext).toEqual("png");
    s.close();
  });

  test('crops around focal point', async () => {
    const s = createOriginServer({
      '/uploads/image.jpg': 'image.jpg'
    });

    const mockS3Client = mockClient(S3Client);
    mockS3Client.on(GetObjectCommand).rejects();
    mockS3Client.on(PutObjectCommand).resolves({});
    mockS3Client.on(PutObjectCommand).resolves({});

    const result = await handle("/testAssets/_1400x300_crop_0.476-0.129/image.jpg?mode=fit&pos=0.4763-0.1287&w=1400&h=300", { status: "403" }, { loggingEnabled: true, verifyQuery: false });

    expect(result.status).toEqual("200");
    // @ts-ignore
    fs.writeFileSync("test.jpg", result.body, 'base64');
    s.close();
  });
});