import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { filtersAtom } from '../../../atoms';

import { useQueryClient } from '@tanstack/react-query';
import { FiltersType } from '../../../models';

interface UseSearch {
  onChange: (event) => void;
  onSearch: (event) => void;
  filters: FiltersType;
}

const useSearch = (): UseSearch => {
  const queryClient = useQueryClient();

  const [filters, setFilters] = useAtom(filtersAtom);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
  };

  const onChange = (event): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const onSearch = (event): void => {
    const enterKeyPressed = equals(event.key, 'Enter');
    if (!enterKeyPressed) {
      return;
    }

    reload();
  };

  return { onChange, onSearch, filters };
};

export default useSearch;
