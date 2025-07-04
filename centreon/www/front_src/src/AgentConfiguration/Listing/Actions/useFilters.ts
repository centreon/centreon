import { SelectEntry } from '@centreon/ui';
import { capitalize } from '@mui/material';

import { useQueryClient } from '@tanstack/react-query';

import { useAtom } from 'jotai';

import { equals, map, pick, propEq, reject } from 'ramda';

import { useEffect, useState } from 'react';
import { filtersAtom } from '../../atoms';
import { AgentType, NamedEntity } from '../../models';
import { labelCMA } from '../../translatedLabels';
import { filtersDefaultValue } from '../../utils';

export const agentTypeOptions = [
  {
    id: AgentType.Telegraf,
    name: capitalize(AgentType.Telegraf)
  },
  {
    id: AgentType.CMA,
    name: labelCMA
  }
];

interface UseFiltersProps {
  changeName: (event) => void;
  changeTypes: (_, types: Array<SelectEntry>) => void;
  changePollers: (_, pollers: Array<SelectEntry>) => void;
  deleteItem: (field: string) => (_, entry: SelectEntry) => void;
  reset: () => void;
  reload: () => void;
  filters;
  isClearDisabled: boolean;
}

export const useFilters = (): UseFiltersProps => {
  const queryClient = useQueryClient();
  const [isClearClicked, setIsClearClicked] = useState(false);

  const [filters, setFilters] = useAtom(filtersAtom);

  const changeName = (event): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const changeTypes = (_, types: Array<SelectEntry>): void => {
    const selectedTypes = map(
      pick(['id', 'name']),
      types || []
    ) as Array<NamedEntity>;

    setFilters({ ...filters, types: selectedTypes });
  };

  const changePollers = (_, pollers: Array<SelectEntry>): void => {
    const selectedpollers = map(
      pick(['id', 'name']),
      pollers || []
    ) as Array<NamedEntity>;

    setFilters({ ...filters, pollers: selectedpollers });
  };

  const deleteItem =
    (name) =>
    (_, option): void => {
      const newItems = reject(propEq(option.id, 'id'), filters[name]);

      setFilters({
        ...filters,
        [name]: newItems
      });
    };
  const isClearDisabled = equals(filters, filtersDefaultValue);

  const reload = (): void => {
    queryClient.invalidateQueries({ queryKey: ['agent-configurations'] });
  };

  const reset = (): void => {
    setFilters(filtersDefaultValue);
    setIsClearClicked(true);
  };

  useEffect(() => {
    if (isClearClicked) {
      reload();
      setIsClearClicked(false);
    }
  }, [filters, isClearClicked]);

  return {
    filters,
    changeName,
    changeTypes,
    changePollers,
    deleteItem,
    reset,
    reload,
    isClearDisabled
  };
};
