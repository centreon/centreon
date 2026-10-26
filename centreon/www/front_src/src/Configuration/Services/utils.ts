import { centreonBaseURL } from '@centreon/ui';

import routeMap from '../../reactRoutes/routeMap';
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

// The legacy listing sorts on the host first, then on the service.
export const defaultSortField = 'host.name';

// The query parameter that opens the listing on one host's services: it
// replaces the filters with a host name filter, then leaves the URL.
export const hostNameSearchParameter = 'host';

export const getServicesOfHostPath = (hostName: string): string =>
  `${routeMap.services}?${hostNameSearchParameter}=${encodeURIComponent(hostName)}`;

// TODO: the React Hosts page has no route yet, so the host form is still the
// legacy one. Once `routeMap.hosts` exists, the switch is this one line:
// `${routeMap.hosts}?mode=edit&id=${encodeURIComponent(id)}`.
export const getHostConfigurationUrl = (id: number): string =>
  `${centreonBaseURL}/main.php?p=60101&o=c&host_id=${encodeURIComponent(id)}`;

// Still a legacy form.

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
