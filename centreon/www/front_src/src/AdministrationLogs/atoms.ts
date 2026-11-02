import { atom } from 'jotai';

import { type Filters, Period } from './models';

export const defaultFilters: Filters = {
  actions: [],
  actors: [],
  from: null,
  period: Period.All,
  search: '',
  targetTypes: [],
  to: null
};

export const filtersAtom = atom<Filters>(defaultFilters);

export const pageAtom = atom(1);

export const dateSortOrderAtom = atom<'asc' | 'desc'>('desc');

export const historyPageAtom = atom(1);

export interface SelectedTarget {
  id: number;
  type: string;
}

export const selectedTargetAtom = atom<SelectedTarget | null>(null);

// The listing and the history panel can show the same log: each one keeps its
// own expanded rows
export const expandedLogIdsAtom = atom<Array<number>>([]);

export const historyExpandedLogIdsAtom = atom<Array<number>>([]);
