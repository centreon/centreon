import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { ChangeEvent, useEffect, useState } from 'react';
import { FilterConfiguration, Filters } from '../../../models';
import { configurationAtom, filtersAtom } from '../../atoms';

interface UseFilters {
  reset: () => void;
  isClearDisabled: boolean;
  change: (key) => (event) => void;
  changeCheckbox: (key) => (event) => void;
  reload: () => void;
  filters: Filters;
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

  const change =
    (key: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      setFilters({ ...filters, [key]: event.target.value });
    };

  const changeCheckbox =
    (key: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      setFilters({ ...filters, [key]: event.target.checked });
    };

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
    change,
    changeCheckbox,
    reload,
    filters,
    filtersConfiguration
  };
};

export default useFilters;
