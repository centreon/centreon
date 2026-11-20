import { atom } from 'jotai';

import { type ActivityLog, type Filters, Period } from './models';

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

// The log whose detail is shown in the side panel. The side panel shows either
// a log or the history of an object, never both
export const selectedLogAtom = atom<ActivityLog | null>(null);

// The log the history was opened from, to go back to its detail. Null when
// the history was opened from the listing
export const historyOriginLogAtom = atom<ActivityLog | null>(null);

export const historyExpandedLogIdsAtom = atom<Array<number>>([]);
