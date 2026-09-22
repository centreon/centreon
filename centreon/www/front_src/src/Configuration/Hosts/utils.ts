import { centreonBaseURL } from '@centreon/ui';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'address',
  'poller',
  'templates'
];

export const filtersInitialValues = {
  name: ''
};

export const filtersAtomKey = 'filters_hosts';
export const columnsAtomKey = 'columns_hosts';

// Host templates are not migrated yet: both the listing column and the form
// input point at the legacy configuration page.
export const getHostTemplateConfigurationUrl = (id: number | string): string =>
  `${centreonBaseURL}/main.php?p=60103&o=c&host_id=${id}`;
