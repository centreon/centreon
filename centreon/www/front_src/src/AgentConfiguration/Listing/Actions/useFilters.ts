import { SelectEntry } from '@centreon/ui';
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

interface UseFiltersProps {
  agentTypes: Array<SelectEntry>;
  pollers: Array<SelectEntry>;
  changeEntries: (field: string) => (_, newEntries: Array<SelectEntry>) => void;
  deleteEntry: (field: string) => (_, entry: SelectEntry) => void;
}

export const useFilters = (): UseFiltersProps => {
  const filters = useAtomValue(filtersAtom);
  const changeFilter = useSetAtom(changeFilterAtom);
  const deleteFilter = useSetAtom(deleteFilterEntryAtom);

  const changeEntries = useCallback(
    (field) => (_, newEntries) => {
      changeFilter({ field, newEntries });
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
    ...filters,
    changeEntries,
    deleteEntry
  };
};
