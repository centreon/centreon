import * as React from 'react';

import axios from 'axios';

import {
  act,
  fireEvent,
  render,
  RenderResult,
  waitFor,
} from '../../../../../testRenderer';
import { buildListingEndpoint } from '../../../../..';

import MultiConnectedAutocompleteField from '.';

const mockedAxios = axios as jest.Mocked<typeof axios>;

mockedAxios.CancelToken = jest.requireActual('axios').CancelToken;

const baseEndpoint = 'endpoint';

const getEndpoint = (parameters): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

const label = 'Multi Connected Autocomplete';

const optionsData = {
  meta: {
    limit: 2,
    page: 1,
    total: 20,
  },
  result: [
    { id: 0, name: 'My Option 1' },
    { id: 1, name: 'My Option 2' },
  ],
};

const renderMultiAutocompleteField = (): RenderResult =>
  render(
    <MultiConnectedAutocompleteField
      field="host.name"
      getEndpoint={getEndpoint}
      label={label}
      placeholder="Type here..."
      value={[optionsData.result[0]]}
    />,
  );

describe(MultiConnectedAutocompleteField, () => {
  beforeEach(() => {
    mockedAxios.get.mockResolvedValue({
      data: optionsData,
    });
  });

  it('excludes selected value ids from the search request', async () => {
    const { getByLabelText } = renderMultiAutocompleteField();

    act(() => {
      fireEvent.click(getByLabelText('Open'));
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        `${baseEndpoint}?page=1&search=${encodeURIComponent(
          '{"$and":[{"id":{"$ni":[0]}}]}',
        )}`,

        expect.anything(),
      );
    });
  });
});
