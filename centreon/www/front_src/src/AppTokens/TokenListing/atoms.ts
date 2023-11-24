import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../storage';

import { defaultSelectedColumnIds } from './ComponentsColumn/models';

export const selectedColumnIdsAtom = atomWithStorage(
  `${baseKey}column-ids`,
  defaultSelectedColumnIds
);
