import { find, propEq } from 'ramda';

import { NamedEntity } from './Listing/models';
import { ParameterKeys } from './Modal/models';

export const defaultParameters = {
  [ParameterKeys.name]: 'my_vcenter',
  [ParameterKeys.url]: 'https://<ip|hostname>/sdk',
  [ParameterKeys.username]: '',
  [ParameterKeys.password]: ''
};

export const availableConnectorTypes = [{ id: 1, name: 'vmware_v6' }];

export const findConnectorTypeById = (id): NamedEntity | undefined => {
  return find(propEq(parseInt(id, 10), 'id'), availableConnectorTypes);
};

export const findConnectorTypeByName = (name): NamedEntity | undefined => {
  return find(propEq(name, 'name'), availableConnectorTypes);
};

export const filtersDefaultValue = {
  name: '',
  pollers: [],
  type: { id: 1, name: 'vmware_v6' }
};
