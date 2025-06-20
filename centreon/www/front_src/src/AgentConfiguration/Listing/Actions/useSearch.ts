import debounce from '@mui/utils/debounce';
import { useAtom } from 'jotai';
import { ChangeEvent, useRef } from 'react';
import { filtersAtom } from '../../atoms';

import { useQueryClient } from '@tanstack/react-query';

interface UseSearchState {
  filters;
  change: (event: ChangeEvent) => void;
}

export const useSearch = (): UseSearchState => {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useAtom(filtersAtom);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['agent-configurations'] });
  };

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((): void => {
      reload();
    }, 500)
  );

  const change = ({ target }): void => {
    setFilters({ ...filters, name: target.value });

    searchDebounced.current(target.value);
  };

  return { filters, change };
};
