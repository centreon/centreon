import { useEffect } from 'react';

import { complement, isEmpty, pluck } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { filtersAtom, searchAtom } from '../../atom';

const useUpdateSearchBarBasedOnFilters = (): void => {
  const filters = useAtomValue(filtersAtom);
  const setSearch = useSetAtom(searchAtom);

  const setSearchValueFromFilters = (): void => {
    const { name, pollers, types } = filters;

    const pollersNames = pluck('name', pollers);
    const typesNames = pluck('name', types);

    const namePart = name ? `name:${name}` : '';
    const typesPart = typesNames.length ? `types:${typesNames.join(',')}` : '';
    const pollersPart = pollersNames.length
      ? `pollers:${pollersNames.join(',')}`
      : '';

    const parts = [namePart, typesPart, pollersPart].filter(
      complement(isEmpty)
    );

    setSearch(parts.join(' '));
  };
  useEffect(() => {
    setSearchValueFromFilters();
  }, [filters.name, filters.pollers, filters.types]);
};

export default useUpdateSearchBarBasedOnFilters;
