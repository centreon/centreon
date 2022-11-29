import withMock from 'storybook-addon-mock';

import { buildListingEndpoint } from '../../../..';
import { SelectEntry } from '../..';
import { Listing } from '../../../../api/models';

import SingleConnectedAutocompleteField from './Single';
import MultiConnectedAutocompleteField from './Multi';

export default {
  decorators: [withMock],
  title: 'InputField/Autocomplete/Connected'
};

const buildEntities = (from): Array<SelectEntry> => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      id: from + index,
      name: `Entity ${from + index}`
    }));
};

const buildResult = (page): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page,
    total: 40
  },
  result: buildEntities((page - 1) * 10)
});

const baseEndpoint = 'endpoint';

const getEndpoint = ({ endpoint, parameters }): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters
  });

const mockSearch = (page: number): object => ({
  delay: 1000,
  method: 'GET',
  response: (request): Listing<SelectEntry> => {
    const { searchParams } = request;

    return buildResult(parseInt(searchParams.page || '0', 10));
  },
  status: 200,
  url: `/endpoint?page=${page}&search=`
});

const getMockData = (): Array<object> => [
  {
    delay: 1000,
    method: 'GET',
    response: (request): Listing<SelectEntry> => {
      const { searchParams } = request;

      return buildResult(parseInt(searchParams.page || '0', 10));
    },
    status: 200,
    url: '/endpoint?page='
  },
  mockSearch(1),
  mockSearch(2),
  mockSearch(3),
  mockSearch(4)
];

export const single = (): JSX.Element => (
  <SingleConnectedAutocompleteField
    field="host.name"
    getEndpoint={(parameters): string => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    label="Single Connected Autocomplete"
    placeholder="Type here..."
  />
);
single.parameters = {
  mockData: getMockData()
};

export const multi = (): JSX.Element => (
  <MultiConnectedAutocompleteField
    field="host.name"
    getEndpoint={(parameters): string => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    label="Multi Connected Autocomplete"
    placeholder="Type here..."
  />
);
multi.parameters = {
  mockData: getMockData()
};
