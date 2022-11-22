/*!
 * Flux
 * Copyright(c) Chris Dyer
 */

import {
  parse as parseQueryString
} from "querystring";
import { CloudFrontRequestHandler } from "aws-lambda";

import { parseRequest, transformPath, validHmacToken} from "./inc/parser";
import { DefaultConfig } from "./inc/config";
import { log } from "./inc/logging";

const handler: CloudFrontRequestHandler = (event, _, callback) => {
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
    return callback(null, request);
  }

  log(config, "Parsing request", request.uri, request.querystring);

  const params = parseQueryString(request.querystring);

  if (config.verifyQuery && !validHmacToken(request, params, config)) {
    log(config, "Forwarding request, query verification failed");
    return callback(null, request);
  }

  const transform = parseRequest(request, params, config);

  if (transform) {
    request.uri = '/' + transformPath(transform);
    log(config, "Modifying path to", request.uri);

    if (transform.sourceFilename) {
      request.headers['x-flux-source-filename'] = [{
        key: "X-Flux-Source-Filename",
        value: transform.sourceFilename
      }];
    }

    return callback(null, request);
  } else {
    log(config, "Forwarding request, unable to parse");
    return callback(null, request);
  }
};

exports.handler = handler;
