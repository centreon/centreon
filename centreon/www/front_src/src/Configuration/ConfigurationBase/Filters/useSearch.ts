import debounce from '@mui/utils/debounce';
import { useAtom, useAtomValue } from 'jotai';
import { equals, pluck } from 'ramda';
import { configurationAtom, filtersAtom } from '../../atoms';

import { useQueryClient } from '@tanstack/react-query';
import { useRef } from 'react';

interface UseSearch {
  onChange: (event) => void;
  filters;
  areAdvancedFiltersVisible: boolean;
}

const useSearch = (): UseSearch => {
  const queryClient = useQueryClient();

  const [filters, setFilters] = useAtom(filtersAtom);
  const configuration = useAtomValue(configurationAtom);

  const filtersConfiguration = configuration?.filtersConfiguration;

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
  };

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((): void => {
      reload();
    }, 500)
  );

  const onChange = ({ target }): void => {
    setFilters({ ...filters, name: target.value });

    searchDebounced.current(target.value);
  };

  const areAdvancedFiltersVisible = !equals(
    pluck('fieldName', filtersConfiguration || []),
    ['name']
  );

  return { onChange, areAdvancedFiltersVisible, filters };
};

export default useSearch;
