import { useEffect, useMemo } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { complement, isEmpty, pluck } from 'ramda';

import { filtersAtom, searchAtom } from '../../atom';

const useUpdateSearchBarBasedOnFilters = (): void => {
  const filters = useAtomValue(filtersAtom);
  const setSearch = useSetAtom(searchAtom);

  const { name, pollers, types } = filters;

  const pollersNames = useMemo(() => pluck('name', pollers), [pollers]);
  const typesNames = useMemo(
    () => pluck('name', types).map((name) => name.replace(' ', '_')),
    [types]
  );

  const namePart = useMemo(() => (name ? `name:${name}` : ''), [name]);
  const typesPart = useMemo(
    () => (typesNames.length ? `types:${typesNames.join(',')}` : ''),
    [typesNames]
  );
  const pollersPart = useMemo(
    () => (pollersNames.length ? `pollers:${pollersNames.join(',')}` : ''),
    [pollersNames]
  );

  const parts = useMemo(
    () => [namePart, typesPart, pollersPart].filter(complement(isEmpty)),
    [namePart, typesPart, pollersPart]
  );

  useEffect(() => {
    setSearch(parts.join(' '));
  }, [parts, setSearch]);
};

export default useUpdateSearchBarBasedOnFilters;
