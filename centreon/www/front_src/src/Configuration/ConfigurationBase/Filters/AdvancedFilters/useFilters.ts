import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useEffect, useState } from 'react';
import { FilterConfiguration, Filters } from '../../../models';
import { configurationAtom, filtersAtom } from '../../atoms';

interface UseFilters {
  reset: () => void;
  isClearDisabled: boolean;
  reload: () => void;
  filtersConfiguration: Array<FilterConfiguration>;
}

const useFilters = (): UseFilters => {
  const queryClient = useQueryClient();

  const [isClearClicked, setIsClearClicked] = useState(false);

  const [filters, setFilters] = useAtom(filtersAtom);
  const configuration = useAtomValue(configurationAtom);

  const filtersConfiguration =
    configuration?.filtersConfiguration as FilterConfiguration[];

  const initialValues = configuration?.filtersInitialValues as Filters;

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
    reset,
    isClearDisabled,
    reload,
    filtersConfiguration
  };
};

export default useFilters;
