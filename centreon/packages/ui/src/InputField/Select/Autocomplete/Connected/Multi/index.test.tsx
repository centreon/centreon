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

const renderMultiAutocompleteField = (customRenderTags?): RenderResult =>
  render(
    <TestQueryProvider>
      <MultiConnectedAutocompleteField
        baseEndpoint=""
        customRenderTags={customRenderTags}
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

  it('display tags when customTagsRender is defined', async () => {
    const customRender = (tags: React.ReactNode): React.ReactNode => (
      <div data-testid="custom-tags-wrapper">{tags}</div>
    );

    const { getByLabelText, getByTestId } =
      renderMultiAutocompleteField(customRender);

    fireEvent.click(getByLabelText('Open'));

    await waitFor(() => {
      const tagChip = getByTestId(
        `tag-option-chip-${optionsData.result[0].id}`
      );
      expect(tagChip).toBeVisible();
      expect(tagChip).toHaveTextContent(optionsData.result[0].name);
    });
  });
});
