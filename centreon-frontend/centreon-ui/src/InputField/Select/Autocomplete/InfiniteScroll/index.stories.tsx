import * as React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import SingleInfiniteAutocompleteField from './Single';
import MultiInfiniteAutocompleteField from './Multi';

import { SelectEntry } from '../..';
import { buildListingEndpoint } from '../../../..';

export default { title: 'InputField/Autocomplete/Infinite' };

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
    pagination: {
      limit: 10,
      page,
      total: 40,
    },
  },
});

const baseEndpoint = 'endpoint';
const getEndpoint = (params): string =>
  buildListingEndpoint({ baseEndpoint, params, searchOptions: ['host.name'] });

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

export const singleInfiniteAutocomplete = (): JSX.Element => (
  <SingleInfiniteAutocompleteField
    label="Single Infinite Autocomplete"
    initialPage={1}
    getEndpoint={getEndpoint}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);

export const multiInfiniteAutocomplete = (): JSX.Element => (
  <MultiInfiniteAutocompleteField
    label="Multi Infinite Autocomplete"
    initialPage={1}
    getEndpoint={getEndpoint}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);
