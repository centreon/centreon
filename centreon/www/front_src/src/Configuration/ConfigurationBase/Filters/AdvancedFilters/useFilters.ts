import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useEffect, useState } from 'react';

import { FilterConfiguration, Filters } from '../../../models';
import { configurationAtom } from '../../atoms';

interface UseFilters {
  reset: () => void;
  isClearDisabled: boolean;
  reload: () => void;
  filtersConfiguration: Array<FilterConfiguration>;
  filtersPanelWidth?: number;
}

interface UseFiltersProps {
  filters: Filters;
  setFilters: (filters: Filters) => void;
}

const useFilters = ({ filters, setFilters }: UseFiltersProps): UseFilters => {
  const queryClient = useQueryClient();

  const [isClearClicked, setIsClearClicked] = useState(false);

  const configuration = useAtomValue(configurationAtom);

  const filtersConfiguration =
    configuration?.filtersConfiguration as Array<FilterConfiguration>;

  const initialValues = configuration?.filtersInitialValues as Filters;

  const filtersPanelWidth = configuration?.filtersPanelWidth;

  const isClearDisabled = equals(filters, initialValues);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listResources'] });
  };

  const reset = (): void => {
    setFilters(initialValues);
    setIsClearClicked(true);
  };

  useEffect(() => {
    if (isClearClicked) {
      reload();
      setIsClearClicked(false);
    }
  }, [filters, isClearClicked]);

  return {
    filtersConfiguration,
    filtersPanelWidth,
    isClearDisabled,
    reload,
    reset
  };
};

export default useFilters;
