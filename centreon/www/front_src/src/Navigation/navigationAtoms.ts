import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import Navigation from './models';

const navigationAtom = atom<Navigation | null>(null);

/** Whether the menu is expanded. Kept across reloads. */
export const isSidebarOpenAtom = atomWithStorage(
  'centreon-navigation-menu-open',
  false
);

/** Whether a submenu popover of the collapsed menu is displayed. */
export const isNavigationPopoverOpenAtom = atom(false);

export default navigationAtom;
