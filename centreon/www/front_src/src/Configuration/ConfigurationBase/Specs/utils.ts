import { ColumnType, buildListingDecoder } from '@centreon/ui';
import { JsonDecoder } from 'ts.data.json';
import { Endpoints, FieldType } from '../../models';

const resourceDecoder = JsonDecoder.object(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    alias: JsonDecoder.nullable(JsonDecoder.string),
    isActivated: JsonDecoder.boolean
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
  result: Array.from({ length: 12 }, (_, i) => ({
    id: i,
    name: `${resourceType} ${i}`,
    alias: `alias for ${resourceType} ${i}`,
    is_activated: !!(i % 2)
  })),
  meta: {
    limit: 10,
    page: 1,
    total: 12
  }
});

export const getEndpoints = (resourceType): Endpoints => ({
  getAll: `/configuration/${resourceType}`,
  getOne: ({ id }) => `/configuration/${resourceType}/${id}`,
  deleteOne: ({ id }) => `/configuration/${resourceType}/${id}`,
  delete: `/configuration/${resourceType}/_delete`,
  duplicate: `/configuration/${resourceType}/_duplicate`,
  enable: `/configuration/${resourceType}/_enable`,
  disable: `/configuration/${resourceType}/_disable`
});

export const columns = [
  {
    disablePadding: false,
    getFormattedString: ({ name }) => name,
    id: 'name',
    label: 'Name',
    sortField: 'name',
    sortable: true,
    type: ColumnType.string
  },
  {
    disablePadding: false,
    getFormattedString: ({ alias }) => alias,
    id: 'alias',
    label: 'Alias',
    sortField: 'alias',
    sortable: true,
    type: ColumnType.string
  }
];

export const filtersConfiguration = [
  {
    name: 'Name',
    fieldName: 'name',
    fieldType: FieldType.Text
  },
  {
    name: 'Alias',
    fieldName: 'alias',
    fieldType: FieldType.Text
  },
  {
    name: 'Status',
    fieldType: FieldType.Status
  }
];

export const filtersInitialValues = {
  name: '',
  alias: '',
  enabled: false,
  disabled: false
};

export const getLabelDeleteOne = (type, name) =>
  `You are about to delete the ${name} ${type}. This action cannot be undone. Do you want to delete it?`;

export const getLabelDeleteMany = (type, count) =>
  `You are about to delete ${count} ${type}. This action cannot be undone. Do you want to delete them?`;

export const getLabelDuplicateOne = (type, name) =>
  `You are about to duplicate the ${name} ${type}. Please specify the number of duplications you would like to make.`;

export const getLabelDuplicateMany = (type, count) =>
  `You are about to duplicate ${count} ${type}. Please specify the number of duplications you would like to make for these groups.`;
