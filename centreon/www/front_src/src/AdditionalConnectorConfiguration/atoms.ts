import { atom } from 'jotai';

import { AdditionalConnectors } from './Listing/models';

export const dialogStateAtom = atom<{
  connector: AdditionalConnectors | null;
  isOpen: boolean;
  variant: 'create' | 'update';
}>({
  connector: null,
  isOpen: false,
  variant: 'create'
});
