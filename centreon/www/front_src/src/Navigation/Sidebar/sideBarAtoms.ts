import { atom } from 'jotai';
import { isNil, keys, omit } from 'ramda';

import { atomWithLocalStorage } from '@centreon/ui';

import { Page } from '../models';

export const selectedNavigationItemsAtom = atomWithLocalStorage<Record<
  string,
  Page
> | null>('selectedNavigationItems', null);

export const hoveredNavigationItemsAtom = atom<Record<string, Page> | null>(
  null
);

export const setHoveredNavigationItemsDerivedAtom = atom(
  null,
  (get, set, { levelName, currentPage }) => {
    const navigationKeysToRemove = keys(get(hoveredNavigationItemsAtom)).filter(
      (navigationItem) => {
        return navigationItem > levelName;
      }
    );

    if (isNil(navigationKeysToRemove)) {
      set(hoveredNavigationItemsAtom, {
        ...get(hoveredNavigationItemsAtom),
        [levelName]: currentPage
      });

      return;
    }
    set(hoveredNavigationItemsAtom, {
      ...omit(navigationKeysToRemove, get(hoveredNavigationItemsAtom)),
      [levelName]: currentPage
    });
  }
);
