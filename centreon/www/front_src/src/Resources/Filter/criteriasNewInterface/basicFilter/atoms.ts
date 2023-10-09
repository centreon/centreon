import { atomWithStorage } from 'jotai/utils';
import { atom } from 'jotai';

import { SelectedResourceType } from '../model';

export const selectedStatusByResourceTypeAtom =
  atomWithStorage<Array<SelectedResourceType> | null>(
    'FilterSelectedStatus',
    null
  );

export const displayActionsAtom = atom(false);
export const displayInformationFilterAtom = atom(false);
