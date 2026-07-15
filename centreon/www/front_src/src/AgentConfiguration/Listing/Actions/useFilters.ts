import { SelectEntry } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useAtom, useSetAtom } from 'jotai';
import { equals, isNil, map, pick } from 'ramda';
import { SyntheticEvent, useEffect, useState } from 'react';

import {
  changeFilterAtom,
  deleteFilterEntryAtom,
  filtersAtom,
  pageAtom
} from '../../atoms';
import { filtersInitialValues, FiltersState } from '../../utils';

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
  const setPage = useSetAtom(pageAtom);
  const changeFilter = useSetAtom(changeFilterAtom);
  const deleteFilterEntry = useSetAtom(deleteFilterEntryAtom);

  const isClearDisabled = equals(filters, filtersInitialValues);

  const changeName = (event): void => {
    changeFilter({ field: 'name', newEntries: event.target.value });
  };

  const changeTypes = (_: SyntheticEvent, types: Array<SelectEntry>): void => {
    const selectedTypes = map(
      pick(['id', 'name']),
      types || []
    ) as Array<NamedEntity>;

    changeFilter({ field: 'type', newEntries: selectedTypes });
  };

  const changerPollers = (_, values: Array<SelectEntry>): void => {
    const pollers = map(
      pick(['id', 'name']),
      values || []
    ) as Array<NamedEntity>;

    changeFilter({ field: 'poller.id', newEntries: pollers });
  };

  const deletePoller = (_, item): void => {
    deleteFilterEntry({ entryToDelete: item, field: 'poller.id' });
  };

  const deleteType = (_, option): void => {
    deleteFilterEntry({ entryToDelete: option, field: 'type' });
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
    setPage(0);

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
