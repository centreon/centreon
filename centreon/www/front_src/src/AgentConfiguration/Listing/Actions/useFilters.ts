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
import { labelCMA } from '../../translatedLabels';

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
  agentTypes: Array<SelectEntry>;
  pollers: Array<SelectEntry>;
  changeEntries: (field: string) => (_, newEntries: Array<SelectEntry>) => void;
  deleteEntry: (field: string) => (_, entry: SelectEntry) => void;
  clearFilters: () => void;
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

  const clearFilters = (): void => {
    changeFilter({ field: 'agentTypes', newEntries: [] });
    changeFilter({ field: 'pollers', newEntries: [] });
  };

  return {
    ...filters,
    changeEntries,
    deleteEntry,
    clearFilters
  };
};
