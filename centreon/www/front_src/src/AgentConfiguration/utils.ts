import { SelectEntry } from '@centreon/ui';

export interface FiltersState {
  name: string;
  'poller.id': Array<SelectEntry>;
  type: Array<SelectEntry>;
}

export const filtersInitialValues: FiltersState = {
  name: '',
  'poller.id': [],
  type: []
};

export const baseKey = 'centreon-app-agent-configuration-25.10-';

export const defaultSelectedColumnIds = ['name', 'type', 'pollers', 'actions'];
