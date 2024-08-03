import { useEffect } from 'react';

import { find, includes, isEmpty, last, pipe, replace, trim } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { filtersAtom, searchAtom } from '../../atom';

const excludeSubstring = (substring) => (str) => replace(substring, '', str);

const useUpdateFiltersBasedOnSearchBar = (): void => {
  const search = useAtomValue(searchAtom);
  const setFilters = useSetAtom(filtersAtom);

  const searchValues = search.split(' ');

  const connectorsTypeSearchValue =
    find((searchItem) => includes('type', searchItem), searchValues) || '';
  const connectorsType = isEmpty(connectorsTypeSearchValue)
    ? ''
    : last(connectorsTypeSearchValue.split(':'));

  const pollersSearchValue =
    find((searchItem) => includes('pollers', searchItem), searchValues) || '';
  const pollers = isEmpty(pollersSearchValue)
    ? ''
    : last(pollersSearchValue.split(':'));

  const nameSearchValue = pipe(
    excludeSubstring(connectorsTypeSearchValue),
    excludeSubstring(pollersSearchValue),
    trim
  )(search);
  const name = last(nameSearchValue.split(':'));

  const getName = (): string | undefined => name;
  const getPollers = (): Array<string> | undefined =>
    isEmpty(pollers) ? [] : pollers?.split(',');
  const getConnectorsType = (): Array<string> | undefined =>
    isEmpty(connectorsType) ? [] : connectorsType?.split(',');

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
  }, [search]);
};

export default useUpdateFiltersBasedOnSearchBar;
