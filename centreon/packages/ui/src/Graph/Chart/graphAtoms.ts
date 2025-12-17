import { atom } from "jotai";

import type { ThresholdTooltip } from "./models";

export const timeTickGraphAtom = atom<Date | null>(null);
export const thresholdTooltipAtom = atom<ThresholdTooltip | null>(null);
