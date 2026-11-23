import { atom } from 'jotai';

import Navigation from './models';

const navigationAtom = atom<Navigation | null>(null);

export const isSidebarOpenAtom = atom(false);

/** Whether a submenu popover of the collapsed menu is displayed. */
export const isNavigationPopoverOpenAtom = atom(false);

export default navigationAtom;
