import type { SelectEntry } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { any, propEq } from 'ramda';

import { filtersAtom, pageAtom } from './atoms';

type FilterKey = 'actions' | 'actors' | 'targetTypes' | 'tokens';

// Click to filter, as in the Okta system log: a value of a row is added to
// the advanced filters
export const useAddFilter = (): ((
  key: FilterKey,
  entry: SelectEntry
) => void) => {
  const setFilters = useSetAtom(filtersAtom);
  const setPage = useSetAtom(pageAtom);

  return (key, entry): void => {
    setPage(1);
    setFilters((current) =>
      any(propEq(entry.id, 'id'), current[key])
        ? current
        : { ...current, [key]: [...current[key], entry] }
    );
  };
};
