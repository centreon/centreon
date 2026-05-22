import debounce from '@mui/utils/debounce';

import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { useRef } from 'react';

import { filtersAtom } from '../../atoms';
import { FiltersState } from '../../utils';

interface UseSearch {
  onChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
  filters: FiltersState;
}

export const useSearch = (): UseSearch => {
  const queryClient = useQueryClient();

  const [filters, setFilters] = useAtom(filtersAtom);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listAgentConfigurations'] });
  };

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((): void => {
      reload();
    }, 500)
  );

  const onChange = ({ target }: React.ChangeEvent<HTMLInputElement>): void => {
    setFilters({ ...filters, name: target.value });

    searchDebounced.current(target.value);
  };

  return { filters, onChange };
};
