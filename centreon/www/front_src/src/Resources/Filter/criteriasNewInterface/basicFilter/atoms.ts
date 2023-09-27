import { atomWithStorage } from 'jotai/utils';

export const selectedStatusByResourceTypeAtom = atomWithStorage(
  'FilterSelectedStatus',
  null
);
