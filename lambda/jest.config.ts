import type { Config } from "jest";
import { createDefaultPreset } from "ts-jest";

export default {
  ...createDefaultPreset(),
} satisfies Config;
