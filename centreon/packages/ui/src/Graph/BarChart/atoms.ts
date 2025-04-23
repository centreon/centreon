import { atom } from 'jotai';

import { TooltipData } from './models';

export const tooltipDataAtom = atom<TooltipData | null>(null);
