import React from 'react';

import axios from 'axios';
import {
  render,
  fireEvent,
  waitFor,
  RenderResult,
} from '@testing-library/react';
import { act } from 'react-test-renderer';

import buildListingEndpoint from '../../../../api/buildListingEndpoint';

import SingleAutocompleteField from './Single';

const mockedAxios = axios as jest.Mocked<typeof axios>;

mockedAxios.CancelToken = jest.requireActual('axios').CancelToken;

const cancelTokenRequestParam = {
  cancelToken: { promise: Promise.resolve({}) },
};

const label = 'Connected Autocomplete';
const placeholder = 'Type here...';

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

const baseEndpoint = 'endpoint';
const getEndpoint = (parameters): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

const renderSingleAutocompleteField = (): RenderResult =>
  render(
    <SingleAutocompleteField
      field="host.name"
      getEndpoint={getEndpoint}
      initialPage={1}
      label={label}
      placeholder="Type here..."
    />,
  );

describe(SingleAutocompleteField, () => {
  beforeEach(() => {
    mockedAxios.get.mockResolvedValue({
      data: optionsData,
    });
  });

  afterEach(() => {
    mockedAxios.get.mockReset();
  });

  it('populates options with the first page result of get call from endpoint', async () => {
    const { getByLabelText, getByText } = renderSingleAutocompleteField();

    act(() => {
      fireEvent.click(getByLabelText('Open'));
    });

    expect(mockedAxios.get).toHaveBeenCalledWith(
      `${baseEndpoint}?page=1`,
      cancelTokenRequestParam,
    );

    await waitFor(() => {
      expect(getByText('My Option 1')).toBeInTheDocument();
    });
  });

  it('populates options with the first page result of the get call from the endpoint after typing something in input field', async () => {
    const {
      getByLabelText,
      getByPlaceholderText,
    } = renderSingleAutocompleteField();

    act(() => {
      fireEvent.click(getByLabelText('Open'));
    });

    expect(mockedAxios.get).toHaveBeenCalledWith(
      `${baseEndpoint}?page=1`,
      cancelTokenRequestParam,
    );

    fireEvent.change(getByPlaceholderText(placeholder), {
      target: { value: 'My Option 2' },
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        `${baseEndpoint}?page=1&search=${encodeURIComponent(
          '{"$or":[{"host.name":{"$rg":"My Option 2"}}]}',
        )}`,

        cancelTokenRequestParam,
      );
    });
  });
});
