export enum FluxSourceType {
  REMOTE = "remote",
  LOCAL = "local",
}

export interface FluxSource {
  handle: string;
  type: string;

  // Remote
  url?: string;

  // S3
  region?: string;
  bucket?: string;
  subFolder?: string;
}

export interface FluxConfig {
  loggingEnabled: boolean;
  rootPrefix: string;
  sources: FluxSource[];

  verifyQuery: boolean;
  verifySecret: string;

  cacheEnabled: boolean;

  bucket: string;
  region: string;

  jpegQuality: number;
  webpQuality: number;

  acceptWebp: boolean;
}

export const DefaultConfig: FluxConfig = {
  loggingEnabled: true,
  rootPrefix: "Flux",
  sources: [],

  verifyQuery: true,
  verifySecret: "",

  cacheEnabled: true,

  bucket: "",
  region: "",

  jpegQuality: 80,
  webpQuality: 80,

  acceptWebp: true,
};
