import { buildListingDecoder, ColumnType, InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { Endpoints, FieldType } from '../../models';

const resourceDecoder = JsonDecoder.object(
  {
    alias: JsonDecoder.nullable(JsonDecoder.string),
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string
  },
  'Resource',
  {
    isActivated: 'is_activated'
  }
);

export const resourceDecoderListDecoder = buildListingDecoder({
  entityDecoder: resourceDecoder,
  entityDecoderName: 'Resource',
  listingDecoderName: 'Resource List'
});

export const getListingResponse = (resourceType) => ({
  meta: {
    limit: 10,
    page: 1,
    total: 12
  },
  result: Array.from({ length: 12 }, (_, i) => ({
    alias: `alias for ${resourceType} ${i}`,
    id: i,
    is_activated: !!(i % 2),
    name: `${resourceType} ${i}`
  }))
});

export const getEndpoints = (resourceType): Endpoints => ({
  create: `/configuration/${resourceType}`,
  delete: `/configuration/${resourceType}/_delete`,
  deleteOne: ({ id }) => `/configuration/${resourceType}/${id}`,
  disable: () => `/configuration/${resourceType}/_disable`,
  duplicate: `/configuration/${resourceType}/_duplicate`,
  enable: () => `/configuration/${resourceType}/_enable`,
  getAll: `/configuration/${resourceType}`,
  getOne: ({ id }) => `/configuration/${resourceType}/${id}`,
  update: ({ id }) => `/configuration/${resourceType}/${id}`
});

export const columns = [
  {
    disablePadding: false,
    getFormattedString: ({ name }) => name,
    id: 'name',
    label: 'Name',
    sortable: true,
    sortField: 'name',
    type: ColumnType.string
  },
  {
    disablePadding: false,
    getFormattedString: ({ alias }) => alias,
    id: 'alias',
    label: 'Alias',
    sortable: true,
    sortField: 'alias',
    type: ColumnType.string
  }
];

export const filtersConfiguration = [
  {
    fieldName: 'name',
    fieldType: FieldType.Text,
    name: 'Name'
  },
  {
    fieldName: 'alias',
    fieldType: FieldType.Text,
    name: 'Alias'
  },
  {
    fieldType: FieldType.Status,
    name: 'Status'
  }
];

export const filtersInitialValues = {
  alias: '',
  disabled: false,
  enabled: false,
  name: ''
};

export const columnsAtomKey = 'columns_configuration';
export const filtersAtomKey = 'filters_configuration';

export const groups = [
  {
    isDividerHidden: true,
    name: 'General informations',
    order: 1
  },
  {
    isDividerHidden: true,
    name: 'Extended informations',
    order: 2
  }
];

export const inputs = [
  {
    fieldName: 'name',
    group: 'General informations',
    label: 'Name',
    type: InputType.Text
  },
  {
    fieldName: 'alias',
    group: 'General informations',
    label: 'Alias',
    type: InputType.Text
  },
  {
    fieldName: 'coordinates',
    group: 'Extended informations',
    label: 'Coordinates',
    type: InputType.Text
  }
];

export const getLabelDeleteOne = (type, name) =>
  `You are about to delete the ${name} ${type}. This action cannot be undone. Do you want to delete it?`;

export const getLabelDeleteMany = (type, count) =>
  `You are about to delete ${count} ${type}. This action cannot be undone. Do you want to delete them?`;

export const getLabelDuplicateOne = (type, name) =>
  `You are about to duplicate the ${name} ${type}. How many duplications would you like to make?`;

export const getLabelDuplicateMany = (type, count) =>
  `You are about to duplicate ${count} ${type}. How many duplications would you like to make?`;
