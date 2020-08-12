import React from 'react';

import axios from 'axios';
import {
  render,
  fireEvent,
  waitFor,
  RenderResult,
} from '@testing-library/react';

import { act } from 'react-test-renderer';
import SingleInfiniteAutocomplete from './Single';
import buildListingEndpoint from '../../../../api/buildListingEndpoint';

const mockedAxios = axios as jest.Mocked<typeof axios>;

mockedAxios.CancelToken = jest.requireActual('axios').CancelToken;

const cancelTokenRequestParam = {
  cancelToken: { promise: Promise.resolve({}) },
};

const label = 'Connected Autocomplete';
const placeholder = 'Type here...';

const optionsData = {
  result: [
    { id: 0, name: 'My Option 1' },
    { id: 1, name: 'My Option 2' },
  ],
  meta: {
    pagination: {
      limit: 2,
      page: 1,
      total: 20,
    },
  },
};

const baseEndpoint = 'endpoint';
const getEndpoint = (parameters): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

const renderSingleInfiniteAutocompleteField = (): RenderResult =>
  render(
    <SingleInfiniteAutocomplete
      label={label}
      initialPage={1}
      getEndpoint={getEndpoint}
      field="host.name"
      placeholder="Type here..."
    />,
  );

describe(SingleInfiniteAutocomplete, () => {
  beforeEach(() => {
    mockedAxios.get.mockResolvedValue({
      data: optionsData,
    });
  });

  afterEach(() => {
    mockedAxios.get.mockReset();
  });

  it('populates options with the first page result of get call from endpoint', async () => {
    const {
      getByLabelText,
      getByText,
    } = renderSingleInfiniteAutocompleteField();

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
    } = renderSingleInfiniteAutocompleteField();

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
        `${baseEndpoint}?page=1&search={"$or":[{"host.name":{"$rg":"My Option 2"}}]}`,
        cancelTokenRequestParam,
      );
    });
  });
});
