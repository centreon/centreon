import React from 'react';

import axios from 'axios';
import {
  render,
  fireEvent,
  waitFor,
  RenderResult,
} from '@testing-library/react';

import ConnectedAutocompleteField from '.';
import { SelectEntry } from '../..';

const mockedAxios = axios as jest.Mocked<typeof axios>;

describe(ConnectedAutocompleteField, () => {
  const label = 'Connected Autocomplete';
  const placeholder = 'Type here...';
  const endpoint = 'endpoint';

  const options = [
    { id: 0, name: 'My Option 1' },
    { id: 1, name: 'My Option 2' },
  ];

  const getSearchEndpoint = (searchValue): string =>
    `${endpoint}?search="${searchValue}"`;

  const renderConnectedAutocompleteField = (): RenderResult =>
    render(
      <ConnectedAutocompleteField
        label={label}
        getOptionsFromResult={(result): Array<SelectEntry> => result}
        baseEndpoint={endpoint}
        placeholder={placeholder}
        getSearchEndpoint={getSearchEndpoint}
      />,
    );

  beforeEach(() => {
    mockedAxios.get.mockResolvedValue({
      data: options,
    });
  });

  afterEach(() => {
    mockedAxios.get.mockReset();
  });

  it('populates options with the result from the get call from the given endpoint', async () => {
    const { getByLabelText, getByText } = renderConnectedAutocompleteField();

    fireEvent.click(getByLabelText('Open'));

    expect(mockedAxios.get).toHaveBeenCalledWith(endpoint, expect.anything());

    await waitFor(() => expect(getByText('My Option 1')).toBeInTheDocument());
  });

  it('populates options with the result of the get call from the given search endpoint when some text is typed', async () => {
    const {
      getByText,
      getByPlaceholderText,
    } = renderConnectedAutocompleteField();

    fireEvent.click(getByText(label));

    fireEvent.change(getByPlaceholderText(placeholder), {
      target: { value: 'My Option 2' },
    });

    await waitFor(() =>
      expect(mockedAxios.get).toHaveBeenCalledWith(
        `${endpoint}?search="My Option 2"`,
        expect.anything(),
      ),
    );
  });
});
