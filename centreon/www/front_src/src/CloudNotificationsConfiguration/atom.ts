import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { NotificationsType } from './models';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');
export const searchAtom = atom<string>('');

export const isPanelOpenAtom = atom<boolean>(false);

export const panelWidthStorageAtom = atomWithStorage(
  'centreon-cloud-notifications-width',
  750
);

export const selectedRowsAtom = atom<Array<NotificationsType>>([]);
