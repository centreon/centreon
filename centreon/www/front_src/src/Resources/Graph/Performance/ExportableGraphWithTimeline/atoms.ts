import type { Interval } from '@centreon/ui';

import { atom } from 'jotai';

export const updatedGraphIntervalAtom = atom<Interval | null>(null);
