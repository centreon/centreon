import { find, propEq } from 'ramda';
import { NamedEntity, ParameterKeys } from './models';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'enabled_hosts_count',
  'disabled_hosts_count',
  'actions',
  'is_activated'
];

// export const filtersInitialValues = {
//   name: '',
//   alias: '',
//   enabled: false,
//   disabled: false
// };

export const defaultParameters = {
  [ParameterKeys.name]: 'my_vcenter',
  [ParameterKeys.url]: 'https://<ip_hostname>/sdk',
  [ParameterKeys.username]: '',
  [ParameterKeys.password]: ''
};

export const availableConnectorTypes = [{ id: 1, name: 'vmware_v6' }];

export const findConnectorTypeById = (id): NamedEntity | undefined => {
  return find(propEq(Number.parseInt(id, 10), 'id'), availableConnectorTypes);
};

export const filtersDefaultValue = {
  name: '',
  pollers: [],
  types: []
};
