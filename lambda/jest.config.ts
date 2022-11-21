import type { Config } from '@jest/types';

export default {
  verbose: true,
  transform: {
    "^.+\\.tsx?$": "ts-jest",
  }
} as Config.InitialOptions;