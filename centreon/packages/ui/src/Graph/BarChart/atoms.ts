import { atom } from 'jotai';

import type { TooltipData } from './models';

export const tooltipDataAtom = atom<TooltipData | null>(null);
