import { describe, expect, test } from "@jest/globals";
import { CloudFrontRequest } from "aws-lambda";
import { createCloudfrontContext, createCloudFrontRequestEvent } from "./utils";
import * as crypto from "crypto";
import { DefaultConfig, FluxConfig } from "../inc/config";

import { handler } from "../request";
import { encodeFilters } from "../inc/parser";

const sampleConfig: FluxConfig = Object.assign({}, DefaultConfig, {
  loggingEnabled: false,
  sources: [
    {
      type: "remote",
      handle: "images",
      url: "http://localhost:4000/uploads/",
    },
  ],
});

async function handle(
  url: string,
  options = {},
  config = {},
): Promise<CloudFrontRequest> {
  const context = createCloudfrontContext();

  let parts = url.split("?");
  const event = createCloudFrontRequestEvent(
    Object.assign(
      {},
      {
        uri: parts[0],
        querystring: parts[1],
      },
      options,
    ),
  );

  // @ts-ignore
  global.fluxConfig = Object.assign({}, sampleConfig, config);
  return await handler(event);
}

describe("request", () => {
  test("parses mode", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=1920&h=1080",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_fit_center-center_80/image.jpg",
    );
  });

  test("parses width and height", async () => {
    const result = await handle(
      "/images/image.jpg?mode=crop&pos=center-center&w=2208&h=1242&q=90",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_2208x1242_crop_center-center_90/image.jpg",
    );
  });

  test("ignores request without a mode", async () => {
    const result = await handle(
      "/images/image.jpg?w=1920&h=1080&q=70",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual("/images/image.jpg");
  });

  test("ignores request without a dimension", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&q=70",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual("/images/image.jpg");
  });

  test("processes request with only one dimension", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_AUTOx920_fit_center-center_70/image.jpg",
    );
  });

  test("allows disabling upscaling", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&upscale=0",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_AUTOx920_fit_center-center_70_ns/image.jpg",
    );
  });

  test("processes deeply nested file", async () => {
    const result = await handle(
      "/images/1/2/3/4/5/6/image.jpg?mode=fit&h=920&q=70",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/1/2/3/4/5/6/_AUTOx920_fit_center-center_70/image.jpg",
    );
  });

  test("processes request with crop position", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_AUTOx920_fit_bottom-center_70/image.jpg",
    );
  });

  test("processes request with focal point", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&pos=0.7259-0.1642&q=70",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_AUTOx920_fit_0.726-0.164_70/image.jpg",
    );
  });

  test("processes request with jpg image format", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=1920&h=1080&f=jpg",
      {
        requestHeaders: {
          accept: [
            {
              key: "accept",
              value: "image/webp",
            },
          ],
        },
      },
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_fit_center-center_80/image.jpg",
    );
  });

  test("processes request with webp image format", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=1920&h=1080&f=webp",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_fit_center-center_80/image.webp",
    );
    expect(result.headers["x-flux-source-filename"]).toEqual([
      {
        key: "X-Flux-Source-Filename",
        value: "image.jpg",
      },
    ]);
  });

  test("processes request with avif image format", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=1920&h=1080&f=avif",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_fit_center-center_60/image.avif",
    );
    expect(result.headers["x-flux-source-filename"]).toEqual([
      {
        key: "X-Flux-Source-Filename",
        value: "image.jpg",
      },
    ]);
  });

  test("processes request with png image format", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=600&f=png",
      {
        requestHeaders: {
          accept: [
            {
              key: "accept",
              value: "image/webp",
            },
          ],
        },
      },
      { verifyQuery: false },
    );
    expect(result.uri).toEqual("/images/_600xAUTO_fit_center-center/image.png");
    expect(result.headers["x-flux-source-filename"]).toEqual([
      {
        key: "X-Flux-Source-Filename",
        value: "image.jpg",
      },
    ]);
  });

  test("validates verification token", async () => {
    const url =
      "images/image.jpg?mode=crop&position=center-center&w=1920&h=1080";

    const signer = crypto.createHmac("sha256", "secret");
    const hmac = signer.update(url).digest("hex");

    const result = await handle(
      "/" + url + "&v=" + hmac,
      {},
      { verifyQuery: true, verifySecret: "secret" },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_crop_center-center_80/image.jpg",
    );
  });

  test("rejects invalid verification token", async () => {
    const url = "/images/image.jpg?mode=fit&w=1920&h=1080";

    const signer = crypto.createHmac("sha256", "secret");
    const hmac = signer.update(url).digest("hex");

    const result = await handle(
      url + "&v=" + hmac,
      {},
      { verifyQuery: true, verifySecret: "secre" },
    );
    expect(result.uri).toEqual("/images/image.jpg");
  });

  test("responds with a webp if accepted", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=1920&h=1080",
      {
        requestHeaders: {
          accept: [
            {
              key: "accept",
              value: "image/webp",
            },
          ],
        },
      },
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_fit_center-center_80/image.webp",
    );
    expect(result.headers["x-flux-source-filename"]).toEqual([
      {
        key: "X-Flux-Source-Filename",
        value: "image.jpg",
      },
    ]);
  });

  test("responds with an avif if accepted", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&w=1920&h=1080",
      {
        requestHeaders: {
          accept: [
            {
              key: "accept",
              value: "image/avif",
            },
          ],
        },
      },
      { verifyQuery: false, acceptAvif: true },
    );
    expect(result.uri).toEqual(
      "/images/_1920x1080_fit_center-center_60/image.avif",
    );
    expect(result.headers["x-flux-source-filename"]).toEqual([
      {
        key: "X-Flux-Source-Filename",
        value: "image.jpg",
      },
    ]);
  });

  test("parses blur filter", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&blur=true",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!gaRibHVyww/image.jpg`,
    );
    expect(encodeFilters({ blur: true })).toEqual("gaRibHVyww");
  });

  test("parses gaussian blur filter", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&blur=31.2",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!gaRibHVyy0A)MzMzMzMz/image.jpg`,
    );
  });

  test("ignores invalid gaussian blur factor", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&blur=0.002",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70/image.jpg`,
    );
  });

  test("parses brightness filter", async () => {
    const filterBlob = encodeFilters({ brightness: -3.1234 });
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&brightness=-3.1234",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!${filterBlob}/image.jpg`,
    );
  });

  test("parses greyscale filter", async () => {
    const filterBlob = encodeFilters({ greyscale: true });
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&greyscale=1",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!${filterBlob}/image.jpg`,
    );
  });

  test("parses hue filter", async () => {
    const filterBlob = encodeFilters({ hue: 50 });
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&hue=50",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!${filterBlob}/image.jpg`,
    );
  });

  test("parses lightness filter", async () => {
    const filterBlob = encodeFilters({ lightness: 180 });
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&lightness=180",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!${filterBlob}/image.jpg`,
    );
  });

  test("ignores invalid lightness filter", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&lightness=abcd",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70/image.jpg`,
    );
  });

  test("parses saturation filter", async () => {
    const filterBlob = encodeFilters({ saturation: 2 });
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&saturation=2",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!${filterBlob}/image.jpg`,
    );
  });

  test("parses tint filter", async () => {
    const result = await handle(
      "/images/image.jpg?mode=fit&h=920&q=70&pos=bottom-center&tint=255,128,0",
      {},
      { verifyQuery: false },
    );
    expect(result.uri).toEqual(
      `/images/_AUTOx920_fit_bottom-center_70_f!gaR0aW50g6FioTChZ6MxMjihcqMyNTU/image.jpg`,
    );
  });
});
