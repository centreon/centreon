import { atom } from 'jotai';

import { TableStyle } from './models';

export const tableStyleAtom = atom<TableStyle>({
  header: {
    backgroundColor: '#666666',
    color: 'white',
    height: 38
  },
  statusColumnChip: {
    height: 20,
    width: 82
  }
});
