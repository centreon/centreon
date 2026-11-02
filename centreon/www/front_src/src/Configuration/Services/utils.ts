import { centreonBaseURL } from '@centreon/ui';

import type { Filters } from './models';

export const defaultSelectedColumnIds = [
  'host',
  'name',
  'scheduling',
  'template',
  'actions',
  'is_activated'
];

export const filtersInitialValues: Filters = {
  disabled: false,
  enabled: false,
  'host.name': '',
  include_disabled_hosts: false,
  name: '',
  template_id: null
};

export const filtersAtomKey = 'filters_services';
export const columnsAtomKey = 'columns_services';

// Sorted on the host, as in the legacy listing.
export const defaultSortField = 'host.name';

// Both forms are still legacy pages.
export const getHostConfigurationUrl = (id: number): string =>
  `${centreonBaseURL}/main.php?p=60101&o=c&host_id=${encodeURIComponent(id)}`;

export const getServiceTemplateConfigurationUrl = (id: number): string =>
  `${centreonBaseURL}/main.php?p=60206&o=c&service_id=${encodeURIComponent(id)}`;

export interface Duration {
  count: number;
  unit: 'min' | 'sec';
}

// An interval is stored as a number of interval lengths. Shown in minutes
// when it falls on a whole minute, in seconds otherwise.
export const toDuration = (
  interval: number | null | undefined,
  intervalLength: number
): Duration | null => {
  if (interval === null || interval === undefined) {
    return null;
  }

  const seconds = interval * intervalLength;

  return seconds % 60 === 0
    ? { count: seconds / 60, unit: 'min' }
    : { count: seconds, unit: 'sec' };
};
