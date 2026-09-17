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
  disabled: false,
  enabled: false,
  is_from_monitoring_connector: false,
  name: '',
  type: []
};

export const filtersAtomKey = 'centreon-commands-filters-1';
export const columnsAtomKey = 'centreon-commands-columns-1';
