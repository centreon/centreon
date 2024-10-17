import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { ShareType } from '../../../api/models';

import { ViewMode } from './models';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');
export const searchAtom = atom<string>('');

export const onlyFavoriteDashboardAtom = atom<boolean>(false);

export const viewModeAtom = atomWithStorage<ViewMode>(
  'dashboards-view-mode',
  ViewMode.List
);

export const askBeforeRevokeAtom = atom<{
  dashboardId: string | number;
  user: {
    id: string | number;
    name: string;
    type: ShareType;
  };
} | null>(null);
