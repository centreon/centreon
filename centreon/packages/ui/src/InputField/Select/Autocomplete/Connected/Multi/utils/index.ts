import { buildListingEndpoint } from '../../../../../..';

export const baseEndpoint = './endpoint';

export const getEndpoint = (parameters): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

export const label = 'Multi Connected Autocomplete';
export const placeholder = 'Type here...';

export const optionsData = {
  meta: {
    limit: 2,
    page: 1,
    total: 20
  },
  result: [
    { id: 0, name: 'My Option 1' },
    { id: 1, name: 'My Option 2' }
  ]
};
