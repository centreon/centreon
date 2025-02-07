import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { filtersAtom } from '../../../atoms';

import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { filtersDefaultValue } from '../../../utils';

interface UseFilters {
  reset: () => void;
  isClearDisabled: boolean;
  change: (key) => (event) => void;
  changeCheckbox: (key) => (event) => void;
  reload: () => void;
}

const useFilters = (): UseFilters => {
  const [isClearClicked, setIsClearClicked] = useState(false);
  const [filters, setFilters] = useAtom(filtersAtom);

  const queryClient = useQueryClient();

  const change =
    (key) =>
    (event): void => {
      setFilters({ ...filters, [key]: event.target.value });
    };

  const changeCheckbox =
    (key) =>
    (event): void => {
      setFilters({ ...filters, [key]: event.target.checked });
    };

  const isClearDisabled = equals(filters, filtersDefaultValue);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
  };

  const reset = (): void => {
    setFilters(() => filtersDefaultValue);
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
    reload
  };
};

export default useFilters;
