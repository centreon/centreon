import { find, propEq } from 'ramda';
import { Filters } from './models';
import { NamedEntity, ParameterKeys } from './models';

export const defaultSelectedColumnIds = [
  'name',
  'type',
  'description',
  'actions'
];

export const getDefaultParameters = (index: number) => ({
  [ParameterKeys.name]: index > 0 ? `my_vcenter_${index}` : 'my_vcenter',
  [ParameterKeys.url]: 'https://<ip_hostname>/sdk',
  [ParameterKeys.username]: '',
  [ParameterKeys.password]: ''
});

export const availableConnectorTypes = [{ id: 1, name: 'vmware_v6' }];

export const findConnectorTypeById = (id): NamedEntity | undefined => {
  return find(propEq(Number.parseInt(id, 10), 'id'), availableConnectorTypes);
};

export const splitURL = (url) => {
  const includesHTTPPrefix = url.match(/https?:\/\//);

  if (!includesHTTPPrefix) {
    return {
      mainURL: url,
      scheme: null
    };
  }

  return {
    mainURL: url.split('://')?.[1],
    scheme: url.split('://')?.[0]
  };
};

export const filtersInitialValues: Filters = {
  name: '',
  enabled: false,
  disabled: false
};

export const filtersAtomKey = 'filters_commands';
export const columnsAtomKey = 'columns_commands';
