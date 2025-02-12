import { useAtom, useAtomValue } from 'jotai';
import { equals, pluck } from 'ramda';
import { configurationAtom, filtersAtom } from '../../atoms';

import { useQueryClient } from '@tanstack/react-query';

interface UseSearch {
  onChange: (event) => void;
  onSearch: (event) => void;
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

  const areAdvancedFiltersVisible = !equals(
    pluck('fieldName', filtersConfiguration || []),
    ['name']
  );

  return { onChange, onSearch, filters, areAdvancedFiltersVisible };
};

export default useSearch;
