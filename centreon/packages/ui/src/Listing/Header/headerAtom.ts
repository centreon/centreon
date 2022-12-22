import { atom } from 'jotai';

import { HeaderTable } from '../models';

export const headerAtom = atom<HeaderTable>({
  backgroundColor: '#666666',
  color: 'white',
  height: 38
});
