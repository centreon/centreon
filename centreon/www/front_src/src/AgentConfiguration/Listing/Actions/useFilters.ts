import { capitalize } from '@mui/material';
import { useAtomValue, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import {
  changeFilterAtom,
  deleteFilterEntryAtom,
  filtersAtom
} from '../../atoms';
import { AgentType } from '../../models';

export const agentTypeOptions = [
  {
    id: AgentType.Telegraf,
    name: capitalize(AgentType.Telegraf)
  }
];

export const useFilters = () => {
  const filters = useAtomValue(filtersAtom);
  const changeFilter = useSetAtom(changeFilterAtom);
  const deleteFilter = useSetAtom(deleteFilterEntryAtom);

  const changeEntries = useCallback(
    (field) => (_, newEntries) => {
      changeFilters({ field, newValue: newEntries });
    },
    []
  );

  const deleteEntry = useCallback(
    (field) => (_, entry) => {
      deleteFilter({ field, entryToDelete: entry });
    },
    []
  );

  return {
    agentTypesFilter: filters.agentTypes,
    changeAgentTypesFilter,
    deleteAgentTypesFilter
  };
};
