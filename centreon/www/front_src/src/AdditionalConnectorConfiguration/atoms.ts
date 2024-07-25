import { atom } from 'jotai';

import { AdditionalConnectorListItem } from './Listing/models';

export const dialogStateAtom = atom<{
  connector: AdditionalConnectorListItem | null;
  isOpen: boolean;
  variant: 'create' | 'update';
}>({
  connector: null,
  isOpen: false,
  variant: 'create'
});
