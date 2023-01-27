import {
  fireEvent,
  getFetchCall,
  mockResponse,
  render,
  RenderResult,
  resetMocks,
  waitFor
} from '../../../../../testRenderer';
import { buildListingEndpoint } from '../../../../..';
import TestQueryProvider from '../../../../../api/TestQueryProvider';

import MultiConnectedAutocompleteField from '.';

const baseEndpoint = 'endpoint';

const getEndpoint = (parameters): string => {
  return buildListingEndpoint({ baseEndpoint, parameters });
};

const label = 'Multi Connected Autocomplete';

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

const renderMultiAutocompleteField = (): RenderResult =>
  render(
    <TestQueryProvider>
      <MultiConnectedAutocompleteField
        field="host.name"
        getEndpoint={getEndpoint}
        label={label}
        placeholder="Type here..."
        value={[optionsData.result[0]]}
      />
    </TestQueryProvider>
  );

describe(MultiConnectedAutocompleteField, () => {
  beforeEach(() => {
    resetMocks();
    mockResponse({ data: optionsData });
  });

  it('excludes selected value ids from the search request', async () => {
    const { getByLabelText } = renderMultiAutocompleteField();

    fireEvent.click(getByLabelText('Open'));

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(`${baseEndpoint}?page=1`);
    });
  });
});
