import debounce from '@mui/utils/debounce';
import { useAtom } from 'jotai';
import {} from 'ramda';
import { filtersAtom } from '../atoms';

import { useQueryClient } from '@tanstack/react-query';
import { useRef } from 'react';
import { Filter } from '../models';

interface UseSearch {
  onChange: (event) => void;
  filters: Filter;
}

const useSearch = (): UseSearch => {
  const queryClient = useQueryClient();

  const [filters, setFilters] = useAtom(filtersAtom);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listTokens'] });
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

  return { onChange, filters };
};

export default useSearch;
