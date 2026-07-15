import debounce from '@mui/utils/debounce';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { useRef } from 'react';

import { changeFilterAtom, filtersAtom } from '../../atoms';
import { FiltersState } from '../../utils';

interface UseSearch {
  onChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
  filters: FiltersState;
}

export const useSearch = (): UseSearch => {
  const queryClient = useQueryClient();

  const filters = useAtomValue(filtersAtom);
  const changeFilter = useSetAtom(changeFilterAtom);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listAgentConfigurations'] });
  };

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((): void => {
      reload();
    }, 500)
  );

  const onChange = ({ target }): void => {
    changeFilter({ field: 'name', newEntries: target.value });

    searchDebounced.current(target.value);
  };

  return { filters, onChange };
};
