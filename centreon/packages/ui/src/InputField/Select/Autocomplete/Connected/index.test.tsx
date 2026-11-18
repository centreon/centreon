import {
  act,
  fireEvent,
  getFetchCall,
  mockResponse,
  type RenderResult,
  render,
  resetMocks,
  waitFor
} from '../../../../../test/testRenderer';
import fetchMock from 'jest-fetch-mock';

import buildListingEndpoint from '../../../../api/buildListingEndpoint';
import type {
  BuildListingEndpointParameters,
  ConditionsSearchParameter
} from '../../../../api/buildListingEndpoint/models';
import TestQueryProvider from '../../../../api/TestQueryProvider';
import SingleConnectedAutocompleteField from './Single';

const label = 'Connected Autocomplete';
const placeholder = 'Type here...';

const optionsData = {
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

const baseEndpoint = 'endpoint';

const getEndpoint = (
  parameters: BuildListingEndpointParameters['parameters']
): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

interface Props {
  searchConditions?: Array<ConditionsSearchParameter>;
}

const mockParams = { headers: { 'Content-Type': 'application/json' } };

const renderSingleConnectedAutocompleteField = (
  { searchConditions }: Props = { searchConditions: undefined }
): RenderResult =>
  render(
    <TestQueryProvider>
      <SingleConnectedAutocompleteField
        baseEndpoint=""
        field="host.name"
        getEndpoint={getEndpoint}
        label={label}
        placeholder="Type here..."
        searchConditions={searchConditions}
      />
    </TestQueryProvider>
  );

describe(SingleConnectedAutocompleteField, () => {
  beforeEach(() => {
    resetMocks();
    mockResponse({ data: optionsData, options: mockParams });
  });

  it('populates options with the first page result from the endpoint request', async () => {
    const { getByLabelText, getByText } =
      renderSingleConnectedAutocompleteField();

    fireEvent.click(getByLabelText('Open'));

    expect(getFetchCall(0)).toEqual(`${baseEndpoint}?page=1`);

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

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(`${baseEndpoint}?page=1`);
    });

    await waitFor(() => {
      expect(getFetchCall(1)).toEqual(`${baseEndpoint}?page=2`);
    });

    fireEvent.change(getByPlaceholderText(placeholder), {
      target: { value: 'My Option 2' }
    });

    await waitFor(() => {
      expect(decodeURIComponent(getFetchCall(2) as string)).toEqual(
        'endpoint?page=1&search={"$and":[{"$and":[{"host.name":{"$lk":"%My Option 2%"}}]}]}'
      );
    });
  });

  it('adds search conditions to the endpoint request when the corresponding prop is passed', async () => {
    const { getByLabelText } = renderSingleConnectedAutocompleteField({
      searchConditions: [
        {
          field: 'parent_name',
          value: {
            $eq: 'Centreon-Server'
          }
        }
      ]
    });

    fireEvent.click(getByLabelText('Open'));

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        `${baseEndpoint}?page=1&search=%7B%22%24and%22%3A%5B%7B%22%24or%22%3A%5B%7B%22parent_name%22%3A%7B%22%24eq%22%3A%22Centreon-Server%22%7D%7D%5D%7D%5D%7D`
      );
    });
  });

  it('leaves no request rejected unhandled when it is closed while its options load', async () => {
    const unhandledRejections: Array<unknown> = [];
    const onUnhandledRejection = (reason: unknown): void => {
      unhandledRejections.push(reason);
    };

    process.on('unhandledRejection', onUnhandledRejection);

    try {
      // Options that never arrive: the request is still running at unmount,
      // which cancels it.
      fetchMock.mockResponse(() => new Promise(() => undefined));

      const { getByLabelText, unmount } =
        renderSingleConnectedAutocompleteField();

      fireEvent.click(getByLabelText('Open'));

      await waitFor(() => {
        expect(getFetchCall(0)).toEqual(`${baseEndpoint}?page=1`);
      });

      unmount();

      // Unhandled rejections are reported once the microtask queue drains.
      await new Promise((resolve) => {
        setTimeout(resolve, 50);
      });

      expect(unhandledRejections).toEqual([]);
    } finally {
      process.off('unhandledRejection', onUnhandledRejection);
    }
  });
});
