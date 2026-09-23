import { centreonBaseURL } from '@centreon/ui';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'address',
  'poller',
  'templates',
  'actions',
  'is_activated'
];

export const filtersInitialValues = {
  disabled: false,
  enabled: false,
  group_id: null,
  name: '',
  poller_id: null,
  template_id: null
};

export const filtersAtomKey = 'filters_hosts';
export const columnsAtomKey = 'columns_hosts';

export const getHostTemplateConfigurationUrl = (id: number): string =>
  `${centreonBaseURL}/main.php?p=60103&o=c&host_id=${encodeURIComponent(id)}`;

// `listServiceByHost.php` reads its host filter from `search`, matching on name.
export const getHostServicesUrl = (name: string): string =>
  `${centreonBaseURL}/main.php?p=60201&search=${encodeURIComponent(name)}`;
