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
  template_id: null
};

export const filtersAtomKey = 'filters_hosts';
export const columnsAtomKey = 'columns_hosts';

// Host templates are not migrated yet: both the listing column and the form
// input point at the legacy configuration page.
export const getHostTemplateConfigurationUrl = (id: number | string): string =>
  `${centreonBaseURL}/main.php?p=60103&o=c&host_id=${id}`;

// The legacy services-by-host listing reads its host filter from `search`
// (`listServiceByHost.php` maps it to `searchH`), and matches on the name.
export const getHostServicesUrl = (name: string): string =>
  `${centreonBaseURL}/main.php?p=60201&search=${encodeURIComponent(name)}`;
