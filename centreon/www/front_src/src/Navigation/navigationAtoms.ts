import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import Navigation from './models';

const navigationAtom = atom<Navigation | null>(null);

export const isSidebarOpenAtom = atomWithStorage<boolean>(
  'centreon-sidebarOpen',
  true
);

export default navigationAtom;
