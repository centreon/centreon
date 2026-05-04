import { SelectEntry } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { equals, isNil, map, pick, propEq, reject } from 'ramda';
import { useEffect, useState } from 'react';

import { filtersAtom } from '../../atoms';
import { filtersInitialValues } from '../../utils';

type NamedEntity = {
  id: number;
  name: string;
};

interface UseFiltersState {
  isClearDisabled: boolean;
  changeName: (event) => void;
  changeTypes: (_, types: Array<SelectEntry>) => void;
  changerPollers: (_, values) => void;
  deletePoller: (_, item) => void;
  deleteType: (_, item) => void;
  isOptionEqualToValue: (option, selectedValue) => boolean;
  reload: () => void;
  reset: () => void;
  filters;
}

export const useFilters = (): UseFiltersState => {
  const queryClient = useQueryClient();

  const [isClearClicked, setIsClearClicked] = useState(false);

  const [filters, setFilters] = useAtom(filtersAtom);

  const isClearDisabled = equals(filters, filtersInitialValues);

  const changeName = (event): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const changeTypes = (_, types: Array<SelectEntry>): void => {
    const selectedTypes = map(
      pick(['id', 'name']),
      types || []
    ) as Array<NamedEntity>;

    setFilters({ ...filters, type: selectedTypes });
  };

  const changerPollers = (_, values): void => {
    const pollers = map(pick(['id', 'name']), values);
    setFilters({ ...filters, 'poller.id': pollers });
  };

  const deletePoller = (_, item): void => {
    const pollers = reject(
      ({ name }) => equals(item.name, name),
      filters['poller.id']
    );

    setFilters({ ...filters, 'poller.id': pollers });
  };

  const deleteType = (_, option): void => {
    const newItems = reject(propEq(option.id, 'id'), filters.type);

    setFilters({
      ...filters,
      type: newItems
    });
  };

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listAgentConfigurations'] });
  };

  const reset = (): void => {
    setFilters(filtersInitialValues);

    setIsClearClicked(true);
  };

  useEffect(() => {
    if (isClearClicked) {
      reload();
      setIsClearClicked(false);
    }
  }, [filters, isClearClicked]);

  return {
    changeName,
    changerPollers,
    changeTypes,
    deletePoller,
    deleteType,
    filters,
    isClearDisabled,
    isOptionEqualToValue,
    reload,
    reset
  };
};
