import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { defaultSelectedColumnIds } from './Columns/Columns';
import { Token } from './models';
import { baseKey } from './storage';

export const selectedColumnIdsAtom = atomWithStorage(
  `${baseKey}column-ids`,
  defaultSelectedColumnIds
);

export const selectedRowAtom = atom<Token | null>(null);
