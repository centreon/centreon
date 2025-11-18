import { Filters } from './models';

export const defaultSelectedColumnIds = [
  'name',
  'command_line',
  'host_uses',
  'service_uses',
  'type',
  'actions',
  'is_activated'
];

export const filtersInitialValues: Filters = {
  name: '',
  enabled: false,
  disabled: false,
  type: []
};

export const filtersAtomKey = 'centreon-commands-filters-1';
export const columnsAtomKey = 'centreon-commands-columns-1';
