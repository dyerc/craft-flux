/*!
 * Flux
 * Copyright(c) Chris Dyer
 */

import { CloudFrontRequestEvent } from "aws-lambda";

import {
  encodeOutboundURI,
  decodeInboundURI,
  parseRequest,
  transformPath,
  validHmacToken,
} from "./inc/parser";
import { DefaultConfig } from "./inc/config";
import { log } from "./inc/logging";

export const handler = async (event: CloudFrontRequestEvent) => {
  const request = event.Records[0].cf.request;
  let config = DefaultConfig;

  // Verify if we have a config bundle
  // eslint-disable-next-line @typescript-eslint/ban-ts-comment
  // @ts-ignore
  if (global && global.fluxConfig) {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    config = Object.assign({}, config, global.fluxConfig);
  } else {
    log(config, "Forwarding request, no config accessible");
    return request;
  }

  log(config, "Parsing request", request.uri, request.querystring);

  // URL arrives with non-ascii characters encoded in the URI, decode the
  // request.uri here as path parsing and verification need the original path.
  request.uri = decodeInboundURI(config, request.uri);

  const urlParams = new URLSearchParams(request.querystring);
  const params = Object.fromEntries(urlParams);

  if (config.verifyQuery && !validHmacToken(request, params, config)) {
    log(config, "Forwarding request, query verification failed");
    return request;
  }

  const transform = parseRequest(request, params, config);

  if (transform) {
    request.uri = "/" + transformPath(transform);
    log(config, "Modifying path to", request.uri);

    if (transform.sourceFilename) {
      request.headers["x-flux-source-filename"] = [
        {
          key: "X-Flux-Source-Filename",
          value: encodeOutboundURI(transform.sourceFilename),
        },
      ];
    }

    return request;
  } else {
    log(config, "Forwarding request, unable to parse");
    return request;
  }
};
