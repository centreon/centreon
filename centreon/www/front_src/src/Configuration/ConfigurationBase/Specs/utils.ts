import {
  buildListingDecoder,
  type Column,
  ColumnType,
  InputType
} from '@centreon/ui';

import { screen } from '@testing-library/react';
import { JsonDecoder } from 'ts.data.json';

import {
  type Endpoints,
  FieldType,
  type FilterConfiguration
} from '../../models';
import { labelEnableDisable } from '../translatedLabels';

interface Resource {
  alias: string | null;
  id: number;
  isActivated: boolean;
  name: string;
}

const resourceDecoder = JsonDecoder.object<Resource>(
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

export const getListingResponse = (resourceType: string) => ({
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

export const getEndpoints = (resourceType: string): Endpoints => ({
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

export const columns: Array<Column> = [
  {
    disablePadding: false,
    getFormattedString: ({ name }): string => name as string,
    id: 'name',
    label: 'Name',
    sortable: true,
    sortField: 'name',
    type: ColumnType.string
  },
  {
    disablePadding: false,
    getFormattedString: ({ alias }): string => alias as string,
    id: 'alias',
    label: 'Alias',
    sortable: true,
    sortField: 'alias',
    type: ColumnType.string
  }
];

export const filtersConfiguration: Array<FilterConfiguration> = [
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

export const hostTemplatesEndpoint = '/configuration/hosts/templates';

export const labelHostTemplate = 'Host template';

export const hostTemplateFieldName = 'host_template.id';

export const getHostTemplatesResponse = () => ({
  meta: {
    limit: 10,
    page: 1,
    total: 2
  },
  result: [
    { id: 1, name: 'host template 1' },
    { id: 2, name: 'host template 2' }
  ]
});

export const filtersConfigurationWithSingleConnectedAutocomplete: Array<FilterConfiguration> =
  [
    ...filtersConfiguration,
    {
      fieldName: hostTemplateFieldName,
      fieldType: FieldType.SingleConnectedAutocomplete,
      getEndpoint: () => hostTemplatesEndpoint,
      name: labelHostTemplate
    }
  ];

export const filtersInitialValues = {
  alias: '',
  disabled: false,
  enabled: false,
  name: ''
};

export const filtersInitialValuesWithSingleConnectedAutocomplete = {
  ...filtersInitialValues,
  [hostTemplateFieldName]: null
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

export const getLabelDeleteOne = (type: string, name: string): string =>
  `You are about to delete the ${name} ${type}. This action cannot be undone. Do you want to delete it?`;

export const getLabelDeleteMany = (type: string, count: number): string =>
  `You are about to delete ${count} ${type}. This action cannot be undone. Do you want to delete them?`;

export const getLabelDuplicateOne = (type: string, name: string): string =>
  `You are about to duplicate the ${name} ${type}. How many duplications would you like to make?`;

export const getLabelDuplicateMany = (type: string, count: number): string =>
  `You are about to duplicate ${count} ${type}. How many duplications would you like to make?`;

// A sentence the page splits across elements (a <strong> around the name or
// the count), found by its whole text: the innermost element holding it.
export const findByWholeText = async (text: string): Promise<HTMLElement> =>
  screen.findByText(
    (_, element) =>
      element?.textContent === text &&
      Array.from(element.children).every((child) => child.textContent !== text)
  );

// The input of a row's Enable/Disable toggle: the test id is on its wrapper.
export const getToggle = (id: number): HTMLInputElement =>
  screen
    .getByTestId(`${labelEnableDisable}_${id}`)
    .querySelector('input') as HTMLInputElement;
