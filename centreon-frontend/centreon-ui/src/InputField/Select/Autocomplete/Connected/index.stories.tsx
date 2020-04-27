/* eslint-disable react-hooks/rules-of-hooks */

import React from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import MultiConnectedAutocompleteField from './Multi';
import SingleConnectedAutocompleteField from './Single';

import { SelectEntry } from '../..';

export default { title: 'InputField/Autocomplete/Connected' };

const mockedAxios = new MockAdapter(axios, { delayResponse: 500 });

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

const baseEndpoint = 'endpoint';
const getSearchEndpoint = (): string => `${baseEndpoint}/search`;

mockedAxios.onGet(baseEndpoint).reply(200, options);

mockedAxios.onGet(`${baseEndpoint}/search`).reply(
  200,
  options.map((option) => ({ ...option, name: `${option.name} searched` })),
);

export const multiWithThreeOptionsRetrieved = (): JSX.Element => (
  <MultiConnectedAutocompleteField
    label="Multi Connected Autocomplete"
    baseEndpoint={baseEndpoint}
    getSearchEndpoint={getSearchEndpoint}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);

export const singleWithThreeOptionsRetrieved = (): JSX.Element => (
  <SingleConnectedAutocompleteField
    label="Single Connected Autocomplete"
    baseEndpoint={baseEndpoint}
    getSearchEndpoint={getSearchEndpoint}
    getOptionsFromResult={(result): Array<SelectEntry> => result}
    placeholder="Type here..."
  />
);
