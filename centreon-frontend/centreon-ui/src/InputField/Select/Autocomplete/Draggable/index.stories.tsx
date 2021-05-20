import * as React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import { SelectEntry } from '../..';
import { buildListingEndpoint } from '../../../..';

import MultiDraggableConnectedAutocompleteField from './MultiConnected';
import MultiDraggableAutocompleteField from './Multi';

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
  meta: {
    limit: 10,
    page,
    total: 40,
  },
  result: buildEntities((page - 1) * 10),
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
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const containedDraggable = (): JSX.Element => (
  <div style={{ width: '400px' }}>
    <MultiDraggable />
  </div>
);

const MultiDraggableConnected = (): JSX.Element => (
  <MultiDraggableConnectedAutocompleteField
    field="host.name"
    getEndpoint={(parameters) => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    initialPage={1}
    label="Multi Draggable Connected Autocomplete"
    placeholder="Type here..."
  />
);

export const draggableConnected = (): JSX.Element => (
  <MultiDraggableConnected />
);

const MultiDraggableError = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    error="Error"
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const draggableWithError = (): JSX.Element => <MultiDraggableError />;

const MultiDraggableRequired = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    required
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const draggableWithRequired = (): JSX.Element => (
  <MultiDraggableRequired />
);

const MultiDraggableInitialValues = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    initialValues={[options[0]]}
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const draggableWithInitialValues = (): JSX.Element => (
  <MultiDraggableInitialValues />
);
