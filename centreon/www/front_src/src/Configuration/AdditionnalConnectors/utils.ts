import { find, propEq } from 'ramda';

import { Filters, NamedEntity, ParameterKeys } from './models';

export const maskedPassword = '********';

export const defaultSelectedColumnIds = [
  'name',
  'type',
  'description',
  'created_by',
  'created_at',
  'updated_by',
  'updated_at',
  'actions'
];

export const getDefaultParameters = (index: number) => ({
  [ParameterKeys.name]: index > 0 ? `my_vcenter_${index}` : 'my_vcenter',
  [ParameterKeys.url]: 'https://<ip_hostname>/sdk',
  [ParameterKeys.username]: '',
  [ParameterKeys.password]: null
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
  'poller.id': [],
  type: []
};

export const filtersAtomKey = 'filters_acc';
export const columnsAtomKey = 'columns_acc';
