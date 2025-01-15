import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { SelectedResourceType } from '../model';

export const selectedStatusByResourceTypeAtom =
  atomWithStorage<Array<SelectedResourceType> | null>(
    'centreon-23.10-resourceStatusRevamp-filterSelectedStatus',
    null
  );

export const displayActionsAtom = atom(false);
export const displayInformationFilterAtom = atom(false);
