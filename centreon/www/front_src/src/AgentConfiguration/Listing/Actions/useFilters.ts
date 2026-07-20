import { SelectEntry } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { equals, isNil, map, pick, propEq, reject } from 'ramda';
import { SyntheticEvent, useEffect, useState } from 'react';

import { filtersAtom } from '../../atoms';
import { FiltersState, filtersInitialValues } from '../../utils';

type NamedEntity = {
  id: number;
  name: string;
};

interface UseFiltersState {
  isClearDisabled: boolean;
  changeName: (event: React.ChangeEvent<HTMLInputElement>) => void;
  changeTypes: (_: SyntheticEvent, types: Array<SelectEntry>) => void;
  changerPollers: (_: SyntheticEvent, values: Array<SelectEntry>) => void;
  deletePoller: (_: SyntheticEvent, item: SelectEntry) => void;
  deleteType: (_: SyntheticEvent, item: SelectEntry) => void;
  isOptionEqualToValue: (
    option: SelectEntry,
    selectedValue: SelectEntry
  ) => boolean;
  reload: () => void;
  reset: () => void;
  filters: FiltersState;
}

export const useFilters = (): UseFiltersState => {
  const queryClient = useQueryClient();

  const [isClearClicked, setIsClearClicked] = useState(false);

  const [filters, setFilters] = useAtom(filtersAtom);

  const isClearDisabled = equals(filters, filtersInitialValues);

  const changeName = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const changeTypes = (_: SyntheticEvent, types: Array<SelectEntry>): void => {
    const selectedTypes = map(
      pick(['id', 'name']),
      types || []
    ) as Array<NamedEntity>;

    setFilters({ ...filters, type: selectedTypes as Array<SelectEntry> });
  };

  const changerPollers = (
    _: SyntheticEvent,
    values: Array<SelectEntry>
  ): void => {
    const pollers = map(pick(['id', 'name']), values) as Array<SelectEntry>;
    setFilters({ ...filters, 'poller.id': pollers });
  };

  const deletePoller = (_: SyntheticEvent, item: SelectEntry): void => {
    const pollers = reject(
      ({ name }: SelectEntry) => equals(item.name, name),
      filters['poller.id']
    );

    setFilters({ ...filters, 'poller.id': pollers });
  };

  const deleteType = (_: SyntheticEvent, option: SelectEntry): void => {
    const newItems = reject(propEq(option.id, 'id'), filters.type);

    setFilters({
      ...filters,
      type: newItems
    });
  };

  const isOptionEqualToValue = (
    option: SelectEntry,
    selectedValue: SelectEntry
  ): boolean => {
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
