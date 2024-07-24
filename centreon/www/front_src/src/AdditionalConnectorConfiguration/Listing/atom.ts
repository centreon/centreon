import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { defaultSelectedColumnIds } from './Columns';
import { AdditionalConnectors, NamedEntity } from './models';
import { filtersDefaultValue } from './ActionsBar/Filters/AdvancedFilters';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const selectedColumnIdsAtom = atomWithStorage(
  `acc-column-ids`,
  defaultSelectedColumnIds
);

export const connectorsToDeleteAtom = atom<AdditionalConnectors | null>(null);
export const connectorsToDuplicateAtom = atom<AdditionalConnectors | null>(
  null
);

export const filtersAtom = atomWithStorage<{
  name: string;
  pollers: Array<NamedEntity>;
  type: NamedEntity;
}>('acc-filters', filtersDefaultValue);
