import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../storage';

import { defaultSelectedColumnIds } from './ComponentsColumn/models';
import { Token } from './models';

export const selectedColumnIdsAtom = atomWithStorage(
  `${baseKey}column-ids`,
  defaultSelectedColumnIds
);

export const clickedRowAtom = atom<Token | null>(null);
export const selectedRowsAtom = atom<Array<Token>>([]);
