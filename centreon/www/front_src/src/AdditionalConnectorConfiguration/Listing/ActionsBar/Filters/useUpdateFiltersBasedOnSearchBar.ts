import { useCallback, useEffect, useMemo } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { find, includes, isEmpty, last, pipe, replace, trim } from 'ramda';

import { filtersAtom, searchAtom } from '../../atom';

const excludeSubstring = (substring) => (str) => replace(substring, '', str);

const useUpdateFiltersBasedOnSearchBar = (): void => {
  const search = useAtomValue(searchAtom);
  const setFilters = useSetAtom(filtersAtom);

  const searchValues = useMemo(() => search.split(' '), [search]);

  const connectorsTypeSearchValue = useMemo(
    () =>
      find((searchItem) => includes('type', searchItem), searchValues) || '',
    [searchValues]
  );

  const connectorsType = useMemo(
    () =>
      isEmpty(connectorsTypeSearchValue)
        ? ''
        : last(connectorsTypeSearchValue.split(':')),
    [connectorsTypeSearchValue]
  );

  const pollersSearchValue = useMemo(
    () =>
      find((searchItem) => includes('pollers', searchItem), searchValues) || '',
    [searchValues]
  );

  const pollers = useMemo(
    () =>
      isEmpty(pollersSearchValue) ? '' : last(pollersSearchValue.split(':')),
    [pollersSearchValue]
  );

  const nameSearchValue = useMemo(
    () =>
      pipe(
        excludeSubstring(connectorsTypeSearchValue),
        excludeSubstring(pollersSearchValue),
        trim
      )(search),
    [connectorsTypeSearchValue, pollersSearchValue, search]
  );

  const name = useMemo(
    () => last(nameSearchValue.split(':')),
    [nameSearchValue]
  );

  const getName = useCallback((): string | undefined => name, [name]);
  const getPollers = useCallback(
    (): Array<string> | undefined =>
      isEmpty(pollers) ? [] : pollers?.split(','),
    [pollers]
  );
  const getConnectorsType = useCallback(
    (): Array<string> | undefined =>
      isEmpty(connectorsType) ? [] : connectorsType?.split(','),
    [connectorsType]
  );

  useEffect(() => {
    setFilters({
      name: getName() || '',
      pollers:
        getPollers()?.map((poller) => ({
          id: Math.floor(Math.random() * 100000) + 1,
          name: poller
        })) || [],
      types:
        getConnectorsType()?.map((type) => ({
          id: Math.floor(Math.random() * 100000) + 1,
          name: type
        })) || []
    });
  }, [search, setFilters, getName, getPollers, getConnectorsType]);
};

export default useUpdateFiltersBasedOnSearchBar;
