import { find, propEq } from 'ramda';

import { NamedEntity } from './Listing/models';
import { ParameterKeys } from './Modal/models';

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
