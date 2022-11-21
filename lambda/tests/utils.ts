import {
  CloudFrontRequest,
  CloudFrontOrigin,
  CloudFrontEvent,
  CloudFrontResponse,
  CloudFrontRequestEvent, Context, CloudFrontResponseEvent, CloudFrontHeaders
} from "aws-lambda";

import path from "path";
import * as http from "http";
import * as fs from "fs";
import * as url from "url";

export const removeNewLineChars = (text: string): string =>
  text.replace(/(\r\n|\n|\r)/gm, "");

export const getNextBinary = (): string =>
  path.join(require.resolve("next"), "../../../../.bin/next");

type CloudFrontEventRequestOptions = {
  uri: string;
  host?: string;
  origin?: CloudFrontOrigin;
  config?: CloudFrontEvent["config"];
  response?: CloudFrontResponse;
  querystring?: string;
  requestHeaders?: { [name: string]: { key: string; value: string }[] };
  method?: string;
  body?: {
    action: "read-only" | "replace";
    data: string;
    encoding: "base64" | "text";
    readonly inputTruncated: boolean;
  };
};

type CloudFrontEventResponseOptions = {
  uri: string;
  host?: string;
  origin?: CloudFrontOrigin;
  config?: CloudFrontEvent["config"];
  response?: CloudFrontResponse;
  querystring?: string;
  requestHeaders?: { [name: string]: { key: string; value: string }[] };
  method?: string;
  status?: string
};

export type OriginRequestEvent = {
  Records: [
    { cf: { request: CloudFrontRequest; config: CloudFrontEvent["config"] } }
  ];
};

export const createCloudFrontRequestEvent = ({
  uri,
  host = "mydistribution.cloudfront.net",
  config = {} as any,
  querystring,
  requestHeaders = {},
  method = "GET",
  body = undefined
}: CloudFrontEventRequestOptions): CloudFrontRequestEvent => ({
  Records: [
    {
      cf: {
        config,
        request: {
          method: method,
          uri,
          clientIp: "1.2.3.4",
          querystring: querystring ?? "",
          headers: {
            host: [
              {
                key: "host",
                value: host
              }
            ],
            ...requestHeaders
          },
          body: body
        }
      }
    }
  ]
});

export const createCloudFrontResponseEvent = ({
  uri,
  host = "mydistribution.cloudfront.net",
  config = {} as any,
  querystring,
  requestHeaders = {},
  method = "GET",
  status = "200"
}: CloudFrontEventResponseOptions): CloudFrontResponseEvent => ({
  Records: [
    {
      cf: {
        config,
        request: {
          method: method,
          uri,
          clientIp: "1.2.3.4",
          querystring: querystring ?? "",
          headers: {
            host: [
              {
                key: "host",
                value: host
              }
            ],
            ...requestHeaders
          }
        },
        response: {
          headers: {},
          status: status,
          statusDescription: 'OK'
        }
      }
    }
  ]
});

export const createCloudfrontContext = (): Context => {
  return {
    callbackWaitsForEmptyEventLoop: false,
    functionName: "func",
    functionVersion: "",
    invokedFunctionArn: "",
    memoryLimitInMB: "512",
    awsRequestId: "id",
    logGroupName: "",
    logStreamName: "",
    getRemainingTimeInMillis(): number {
      return 0;
    },
    done(error?: Error, result?: any) {
    },
    fail(error: Error | string) {
    },
    succeed(messageOrObject: any) {
    }
  };
}

export const createOriginServer = (paths = {}) => {
  return http.createServer((req, res) => {
    if (req.url != null) {
      const parsedUrl = url.parse(req.url);
      // @ts-ignore
      let pathname = paths[parsedUrl.pathname];

      if (pathname) {
        const ext = path.extname(pathname);

        const map = {
          '.html': 'text/html',
          '.png': 'image/png',
          '.jpg': 'image/jpeg',
          '.svg': 'image/svg+xml'
        };

        fs.readFile(path.join(__dirname, "fixtures", pathname), function (err, data) {
          if (err) {
            if (err.code === 'ENOENT') {
              res.statusCode = 404;
              res.end(`File ${pathname} not found!`);
            } else {
              res.statusCode = 500;
              res.end(`Error getting the file: ${err}.`);
            }
          } else {
            // @ts-ignore
            res.setHeader('Content-type', map[ext] || 'text/plain');
            res.end(data);
          }
        });
      } else {
        res.statusCode = 404;
        res.end(`Path not in mapping`);
      }
    } else {
      res.end();
    }
  }).listen(4000);
}