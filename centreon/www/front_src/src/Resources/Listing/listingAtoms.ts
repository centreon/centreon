import { atom } from 'jotai';

import { atomWithLocalStorage } from '@centreon/ui';

import { ResourceListing } from '../models';
import { baseKey } from '../storage';

import { defaultSelectedColumnIds } from './columns';

export const listingAtom = atom<ResourceListing | undefined>(undefined);
export const limitAtom = atomWithLocalStorage(`${baseKey}limit`, 30);
export const pageAtom = atom<number | undefined>(undefined);
export const enabledAutorefreshAtom = atom<boolean>(true);
export const selectedColumnIdsAtom = atomWithLocalStorage(
  `${baseKey}column-ids`,
  defaultSelectedColumnIds
);
export const sendingAtom = atom<boolean>(false);
