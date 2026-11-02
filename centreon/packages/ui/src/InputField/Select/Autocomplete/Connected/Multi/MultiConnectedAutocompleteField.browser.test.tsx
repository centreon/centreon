import {
  Method,
  MultiConnectedAutocompleteField,
  type SelectEntry,
  TestQueryProvider
} from '@centreon/ui';

import { interceptAPIRequest, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { useState } from 'react';
import { initReactI18next } from 'react-i18next';

import { labelSelectAll, labelUnSelectAll } from '../../../../translatedLabels';
import { baseEndpoint, getEndpoint, label, placeholder } from './utils';

const optionOne = 'My Option 1';

// The option list and search result the endpoint mocks return.
const listOptions = {
  meta: { limit: 5, page: 1, total: 5 },
  result: [
    { id: 0, name: 'My Option 1' },
    { id: 1, name: 'My Option 2' },
    { id: 2, name: 'My Option 3' },
    { id: 3, name: 'My Option 4' },
    { id: 4, name: 'My Option 5' }
  ]
};

const searchedOption = {
  meta: { limit: 1, page: 1, total: 1 },
  result: [{ id: 0, name: 'My Option 1' }]
};

const optionNames = listOptions.result.map(({ name }) => name);

// Passed down to the multi autocomplete, though the connected field's type
// does not declare it.
const multiAutocompleteProps: Record<string, unknown> = {
  disableSelectAll: false
};

const Component = (): JSX.Element => {
  const [values, setValues] = useState<Array<SelectEntry>>([]);

  return (
    <TestQueryProvider>
      <div style={{ paddingTop: 20 }}>
        <MultiConnectedAutocompleteField
          {...multiAutocompleteProps}
          field="host.name"
          getEndpoint={getEndpoint}
          label={label}
          onChange={(_, item) => setValues(item as Array<SelectEntry>)}
          placeholder={placeholder}
          value={values}
        />
      </div>
    </TestQueryProvider>
  );
};

const getInput = (): HTMLInputElement =>
  document.querySelector(
    '[data-testid="Multi Connected Autocomplete"]'
  ) as HTMLInputElement;

// The last one: a closing list can still be in the page.
const findListOptions = async (): Promise<HTMLElement> =>
  (await screen.findAllByRole('presentation')).at(-1) as HTMLElement;

const getOptionTexts = (listOptionsElement: HTMLElement): Array<string> =>
  within(listOptionsElement)
    .queryAllByText(/^My Option \d$/)
    .map((option) => option.textContent ?? '');

const getOptionCheckboxes = (listOptionsElement: HTMLElement) =>
  within(listOptionsElement).getAllByRole('checkbox');

describe('Multi connected autocomplete', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  beforeEach(() => {
    i18next.use(initReactI18next).init({
      lng: 'en',
      resources: {}
    });

    interceptAPIRequest({
      alias: 'getListOptions',
      method: Method.GET,
      path: `${baseEndpoint}**`,
      response: listOptions
    });

    interceptAPIRequest({
      alias: 'getSearchedOption',
      method: Method.GET,
      path: `${baseEndpoint}**`,
      query: {
        name: 'search',
        value: JSON.stringify({
          $and: [{ $and: [{ 'host.name': { $lk: `%${optionOne}%` } }] }]
        })
      },
      response: searchedOption
    });

    render(<Component />);
  });

  it('displays and hides the list when the user double-clicks on the input', async () => {
    expect((await screen.findAllByText(label))[0]).toBeVisible();

    const input = getInput();

    await userEvent.click(input);

    await waitForRequest('@getListOptions');

    expect(input).toHaveAttribute('placeholder', placeholder);

    const listOptionsElement = await findListOptions();

    expect(listOptionsElement).toBeVisible();

    // The list re-renders its options: compared by text, not by element.
    await waitFor(() => {
      expect(getOptionTexts(listOptionsElement)).toEqual(optionNames);
    });

    // The count of options, the select all button and the five options
    // unchecked.
    expect(
      within(listOptionsElement).getByText('5 element(s) found')
    ).toBeVisible();
    expect(within(listOptionsElement).getByText(labelSelectAll)).toBeVisible();
    getOptionCheckboxes(listOptionsElement).forEach((checkbox) => {
      expect(checkbox).not.toBeChecked();
    });

    await userEvent.click(input);

    await waitFor(() => {
      expect(listOptionsElement).not.toBeInTheDocument();
    });

    // No option list, and nothing selected.
    expect(screen.queryByRole('presentation')).not.toBeInTheDocument();
    expect(screen.queryByTestId('CancelIcon')).not.toBeInTheDocument();
  });

  it('displays all options on the list when the user searches for and selects an option', async () => {
    const input = getInput();

    await userEvent.click(input);

    await waitForRequest('@getListOptions');

    const listOptionsElement = await findListOptions();

    expect(listOptionsElement).toBeVisible();

    await userEvent.type(input, optionOne);

    await waitForRequest('@getSearchedOption');

    await waitFor(() => {
      expect(listOptionsElement.querySelectorAll('li')).toHaveLength(6);
    });

    await userEvent.click(document.querySelectorAll('[type="checkbox"]')[0]);

    const inputContainer = input.parentElement as HTMLElement;

    expect(await within(inputContainer).findByText(optionOne)).toBeVisible();
    expect(within(inputContainer).getByTestId('CancelIcon')).toBeVisible();

    await waitForRequest('@getListOptions');

    await waitFor(() => {
      expect(listOptionsElement.querySelectorAll('li')).toHaveLength(
        listOptions.result.length + 1
      );
    });

    // The list re-renders its options: compared by text, not by element.
    await waitFor(() => {
      expect(getOptionTexts(listOptionsElement)).toEqual(optionNames);
    });
  });

  it('checks all options when Select all button is clicked', async () => {
    const input = getInput();

    await userEvent.click(input);

    expect(await screen.findByText('5 element(s) found')).toBeInTheDocument();

    await waitForRequest('@getListOptions');

    await userEvent.click(await screen.findByText(labelSelectAll));

    expect(await screen.findByText(labelUnSelectAll)).toBeVisible();

    await waitFor(() => {
      expect(screen.getAllByTestId('CancelIcon')).toHaveLength(5);
    });

    // A chip per option in the field, in order, and every option checked.
    expect(
      within(input.parentElement as HTMLElement)
        .getAllByText(/^My Option \d$/)
        .map((chip) => chip.textContent)
    ).toEqual(optionNames);
    getOptionCheckboxes(await findListOptions()).forEach((checkbox) => {
      expect(checkbox).toBeChecked();
    });
  });

  it('unchecks all options when unSelect all button is clicked', async () => {
    const input = getInput();

    await userEvent.click(input);

    expect(await screen.findByText('5 element(s) found')).toBeInTheDocument();

    await waitForRequest('@getListOptions');

    await userEvent.click(await screen.findByText(labelSelectAll));
    await userEvent.click(await screen.findByText(labelUnSelectAll));

    expect(await screen.findByText(labelSelectAll)).toBeVisible();

    await waitFor(() => {
      expect(screen.queryAllByTestId('CancelIcon')).toHaveLength(0);
    });

    // The field is empty and every option unchecked.
    expect(input).toHaveAttribute('placeholder', placeholder);
    getOptionCheckboxes(await findListOptions()).forEach((checkbox) => {
      expect(checkbox).not.toBeChecked();
    });
  });
});
