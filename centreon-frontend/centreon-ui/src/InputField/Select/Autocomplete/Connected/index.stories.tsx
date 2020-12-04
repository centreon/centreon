import * as React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import { SelectEntry } from '../..';
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

export const single = (): JSX.Element => (
  <SingleConnectedAutocompleteField
    label="Single Connected Autocomplete"
    field="host.name"
    initialPage={1}
    getEndpoint={(parameters) => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);

export const multi = (): JSX.Element => (
  <MultiConnectedAutocompleteField
    label="Multi Connected Autocomplete"
    initialPage={1}
    field="host.name"
    getEndpoint={(parameters) => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);
