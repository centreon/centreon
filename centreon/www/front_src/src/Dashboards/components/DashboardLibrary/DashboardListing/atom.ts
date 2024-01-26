import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { Dashboard } from '../../../api/models';

import { ViewMode } from './models';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');
export const searchAtom = atom<string>('');

export const selectedRowsAtom = atom<Array<Dashboard>>([]);
export const viewModeAtom = atomWithStorage<ViewMode>(
  'dashboards-view-mode',
  ViewMode.Cards
);
