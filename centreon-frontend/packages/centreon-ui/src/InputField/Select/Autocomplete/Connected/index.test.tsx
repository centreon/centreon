import * as React from 'react';

import axios from 'axios';
import {
  render,
  fireEvent,
  waitFor,
  RenderResult,
} from '@testing-library/react';
import { act } from 'react-test-renderer';

import buildListingEndpoint from '../../../../api/buildListingEndpoint';
import { ConditionsSearchParameter } from '../../../../api/buildListingEndpoint/models';

import SingleConnectedAutocompleteField from './Single';

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

interface Props {
  searchConditions?: Array<ConditionsSearchParameter>;
}

const renderSingleConnectedAutocompleteField = (
  { searchConditions }: Props = { searchConditions: undefined },
): RenderResult =>
  render(
    <SingleConnectedAutocompleteField
      field="host.name"
      getEndpoint={getEndpoint}
      label={label}
      placeholder="Type here..."
      searchConditions={searchConditions}
    />,
  );

describe(SingleConnectedAutocompleteField, () => {
  beforeEach(() => {
    mockedAxios.get.mockResolvedValue({
      data: optionsData,
    });
  });

  afterEach(() => {
    mockedAxios.get.mockReset();
  });

  it('populates options with the first page result from the endpoint request', async () => {
    const { getByLabelText, getByText } =
      renderSingleConnectedAutocompleteField();

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

  it('populates options with the first page result from the endpoint request after typing something in input field', async () => {
    const { getByLabelText, getByPlaceholderText } =
      renderSingleConnectedAutocompleteField();

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
          '{"$and":[{"host.name":{"$lk":"%My Option 2%"}}]}',
        )}`,

        cancelTokenRequestParam,
      );
    });
  });

  it('adds search conditions to the endpoint request when the corresponding prop is passed', async () => {
    const { getByLabelText } = renderSingleConnectedAutocompleteField({
      searchConditions: [
        {
          field: 'parent_name',
          value: {
            $eq: 'Centreon-Server',
          },
        },
      ],
    });

    act(() => {
      fireEvent.click(getByLabelText('Open'));
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        `${baseEndpoint}?page=1&search=${encodeURIComponent(
          '{"$and":[{"parent_name":{"$eq":"Centreon-Server"}}]}',
        )}`,

        cancelTokenRequestParam,
      );
    });
  });
});
