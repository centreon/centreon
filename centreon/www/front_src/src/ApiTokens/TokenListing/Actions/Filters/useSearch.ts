import { useRef } from 'react';

import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';

import debounce from '@mui/utils/debounce';

import { filtersAtom } from '../../atoms';
import { Filter } from '../../models';

interface UseSearch {
  filters: Filter;
  onChange: (event) => void;
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

  return { filters, onChange };
};

export default useSearch;
