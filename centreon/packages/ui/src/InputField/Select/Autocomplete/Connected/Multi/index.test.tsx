import {
  RenderResult,
  fireEvent,
  getFetchCall,
  mockResponse,
  render,
  resetMocks,
  waitFor
} from '../../../../../../test/testRenderer';
import TestQueryProvider from '../../../../../api/TestQueryProvider';

import { baseEndpoint, getEndpoint, label, optionsData } from './utils';

import MultiConnectedAutocompleteField from '.';

const renderMultiAutocompleteField = (): RenderResult =>
  render(
    <TestQueryProvider>
      <MultiConnectedAutocompleteField
        baseEndpoint=""
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
