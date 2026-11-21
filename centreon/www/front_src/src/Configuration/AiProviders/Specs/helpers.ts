import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { idPrefix } from '../Form/useFormInputs';
import { labelApiToken, labelType } from '../translatedLabels';
import initialize from './initialize';

export const nameTestId = `${idPrefix}-name`;
export const descriptionTestId = `${idPrefix}-description`;
export const endpointUrlTestId = `${idPrefix}-endpoint-url`;
export const isActivatedTestId = `${idPrefix}-is-activated`;

export const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAiProviders');
};

export const openCreationForm = async (): Promise<void> => {
  await initializeListing();

  await userEvent.click(await screen.findByTestId('add-resource'));
};

// opens provider 1 from the listing and waits for its details
export const openProvider = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  await initializeListing(props);

  await userEvent.click(await screen.findByText('Anthropic production'));

  await waitForRequest('@getAiProvider');
};

export const getPanel = (): Promise<HTMLElement> =>
  screen.findByTestId(panelDataTestIds.content);

// `@centreon/ui` text fields put the test id on the wrapper and the input.
export const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

export const getName = (): Promise<HTMLInputElement> => getInput(nameTestId);

export const getApiToken = (): Promise<HTMLInputElement> =>
  getInput(labelApiToken);

// the custom fields put their label as the test id of the input itself
export const getCustomInput = (label: string): Promise<HTMLInputElement> =>
  screen.findByTestId(label) as Promise<HTMLInputElement>;

export const getSaveButton = (): HTMLElement | null =>
  screen.queryByTestId(panelDataTestIds.save);

// the provider type is a dropdown: open it, then pick the option by its label
export const selectType = async (type: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(labelType));
  await userEvent.click(await screen.findByRole('option', { name: type }));
  await waitFor(() => expect(screen.getByTestId(labelType)).toHaveValue(type));
};

export const replaceValue = async (
  input: HTMLElement,
  value: string
): Promise<void> => {
  await userEvent.clear(input);
  await userEvent.type(input, value);
};

// the form validates asynchronously (the name lookup is debounced): the save
// button is enabled once it is done
export const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled(), {
    timeout: 3000
  });

  await userEvent.click(getSaveButton() as HTMLElement);
};

export const queryAllInPanel = async (
  testId: string
): Promise<Array<HTMLElement>> =>
  within(await getPanel()).queryAllByTestId(testId);
