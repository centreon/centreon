import { atomWithStorage } from 'jotai/utils';

import { SelectedResourceType } from '../model';

export const selectedStatusByResourceTypeAtom =
  atomWithStorage<Array<SelectedResourceType> | null>(
    'FilterSelectedStatus',
    null
  );
