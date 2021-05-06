import * as React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import { buildListingEndpoint } from '../../../..';

import SingleConnectedAutocompleteField from './Single';
import MultiConnectedAutocompleteField from './Multi';

export default { title: 'InputField/Autocomplete/Connected' };

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

export const single = (): JSX.Element => (
  <SingleConnectedAutocompleteField
    field="host.name"
    getEndpoint={(parameters) => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    label="Single Connected Autocomplete"
    placeholder="Type here..."
  />
);

export const multi = (): JSX.Element => (
  <MultiConnectedAutocompleteField
    field="host.name"
    getEndpoint={(parameters) => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    label="Multi Connected Autocomplete"
    placeholder="Type here..."
  />
);
