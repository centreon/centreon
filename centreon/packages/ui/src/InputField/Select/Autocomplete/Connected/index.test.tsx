import {
  render,
  fireEvent,
  waitFor,
  RenderResult,
  act,
  resetMocks,
  mockResponse,
  getFetchCall
} from '../../../../testRenderer';
import buildListingEndpoint from '../../../../api/buildListingEndpoint';
import { ConditionsSearchParameter } from '../../../../api/buildListingEndpoint/models';
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

const getEndpoint = (parameters): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

interface Props {
  searchConditions?: Array<ConditionsSearchParameter>;
}

const renderSingleConnectedAutocompleteField = (
  { searchConditions }: Props = { searchConditions: undefined }
): RenderResult =>
  render(
    <TestQueryProvider>
      <SingleConnectedAutocompleteField
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
    mockResponse({ data: optionsData });
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
      expect(getFetchCall(2)).toEqual(
        `${baseEndpoint}?page=1&search=${encodeURIComponent(
          '{"$and":[{"host.name":{"$lk":"%My Option 2%"}}]}'
        )}`
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
        `${baseEndpoint}?page=1&search=${encodeURIComponent(
          '{"$and":[{"parent_name":{"$eq":"Centreon-Server"}}]}'
        )}`
      );
    });
  });
});
