import { FluxConfig } from "./config";

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function log(config: FluxConfig, ...args: any) {
  if (config.loggingEnabled) {
    console.log(args);
  }
}
