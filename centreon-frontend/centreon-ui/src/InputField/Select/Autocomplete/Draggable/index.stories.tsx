import * as React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import MultiDraggableConnectedAutocompleteField from './MultiConnected';
import MultiDraggableAutocompleteField from './Multi';

import { SelectEntry } from '../..';
import { buildListingEndpoint } from '../../../..';

export default { title: 'InputField/Autocomplete/Draggable' };

const buildEntities = (from) => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      id: from + index,
      name: `Entity ${from + index}`,
    }));
};

const buildResult = (page) => ({
  result: buildEntities((page - 1) * 10),
  meta: {
    limit: 10,
    page,
    total: 40,
  },
});

const baseEndpoint = 'endpoint';

const getEndpoint = ({ endpoint, parameters }): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters,
  });

const mockedAxios = new MockAdapter(axios, { delayResponse: 500 });

mockedAxios
  .onGet(
    /endpoint\?page=\d+(?:&search={"\$or":\[{"host\.name":{"\$rg":".*"}}]})?/,
  )
  .reply((config) => {
    return [
      200,
      buildResult(parseInt(config.url?.split('page=')[1][0] || '0', 10)),
    ];
  });

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

const MultiDraggable = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    options={options}
    label="Draggable Autocomplete"
    placeholder="Type here..."
    open
  />
);

export const draggable = (): JSX.Element => <MultiDraggable />;

const MultiDraggableConnected = (): JSX.Element => (
  <MultiDraggableConnectedAutocompleteField
    label="Multi Draggable Connected Autocomplete"
    initialPage={1}
    field="host.name"
    getEndpoint={(parameters) => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);

export const draggableConnected = (): JSX.Element => (
  <MultiDraggableConnected />
);

const MultiDraggableError = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    options={options}
    label="Draggable Autocomplete"
    placeholder="Type here..."
    error="Error"
  />
);

export const draggableWithError = (): JSX.Element => <MultiDraggableError />;

const MultiDraggableRequired = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    options={options}
    label="Draggable Autocomplete"
    placeholder="Type here..."
    required
  />
);

export const draggableWithRequired = (): JSX.Element => (
  <MultiDraggableRequired />
);

const MultiDraggableInitialValues = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    options={options}
    label="Draggable Autocomplete"
    placeholder="Type here..."
    initialValues={[options[0]]}
  />
);

export const draggableWithInitialValues = (): JSX.Element => (
  <MultiDraggableInitialValues />
);
