/*!
 * Flux
 * Copyright(c) Chris Dyer
 */

import {
  CloudFrontResponseHandler,
  CloudFrontResponseResult,
} from "aws-lambda";

import { DefaultConfig } from "./inc/config";
import { compilePath, parseRequest, removeRootPrefix } from "./inc/parser";
import { fetchSource, transformSource, writeFile } from "./inc/processor";
import { log } from "./inc/logging";

const handler: CloudFrontResponseHandler = (event, _, callback) => {
  const { request, response } = event.Records[0].cf;
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
    return callback(null, response);
  }

  log(config, "Handling origin response", request.uri, request.querystring);

  // Only handle cache misses
  if (response.status === "403" || response.status === "404") {
    const urlParams = new URLSearchParams(request.querystring);
    const params = Object.fromEntries(urlParams);
    const transform = parseRequest(request, params, config);

    if (transform) {
      fetchSource(transform, config)
        .then((buffer) => transformSource(buffer, transform))
        .then((buffer) => {
          /*
            In theory, we could write the transformed file to S3 asynchronously as we return it to the user
            however, in the event that it's too big, and we have to redirect them, we need to ensure the
            transformed file is outputted and ready for CloudFront to return.
           */
          return writeFile(
            compilePath(config.rootPrefix, request.uri.substring(1)),
            buffer,
            config
          );
        })
        .then((buffer) => {
          response.headers["server"] = [{ key: "Server", value: "Flux" }];

          if (buffer.length > 512 * 1024) {
            let repeatRequest = removeRootPrefix(request.uri, config);

            /*
              For S3 buckets that have public access blocked, we need to ensure the repeat request
              goes through CloudFront in order that it doesn't trigger a 403 error
             */
            if (request.headers["x-flux-original-request"]) {
              const cloudfrontHost =
                request.headers["x-flux-original-request"][0].value;
              repeatRequest = `https://${cloudfrontHost}?${request.querystring}&redir=1`;
            }

            log(
              config,
              "Re-handling request to return large body response",
              repeatRequest
            );

            response.headers["Location"] = [
              { key: "Location", value: repeatRequest },
            ];
            response.headers["x-reason"] = [
              { key: "X-Reason", value: "Generated by Flux" },
            ];

            return callback(
              null,
              Object.assign({}, response as CloudFrontResponseResult, {
                status: "302",
                statusDescription: "Moved Temporarily",
              })
            );
          } else {
            response.headers["content-type"] = [
              { key: "Content-Type", value: "image/" + transform.extension },
            ];

            // Directly return the body because it should be under the CloudFront 1MB threshold
            return callback(
              null,
              Object.assign({}, response as CloudFrontResponseResult, {
                status: "200",
                body: buffer.toString("base64"),
                bodyEncoding: "base64",
              })
            );
          }
        })
        .catch((error) => {
          log(config, "Unable to fetch and transform", error);
          response.status = "404";
          response.statusDescription = "Not Found";
          return callback(null, response);
        });
    } else {
      log(config, "Forwarding request, unable to parse");
      return callback(null, response);
    }
  } else {
    return callback(null, response);
  }
};

exports.handler = handler;
