import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelChildHosts,
  labelHostGroups,
  labelParentHosts
} from '../../Hosts/translatedLabels';
import initialize from './initialize';

const testIds = {
  categories: 'host-template-form-categories',
  severity: 'host-template-form-severity'
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: the listing behind the panel shows the same names.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const getChips = (testId: string): Array<string | null> =>
  Array.from(
    screen
      .getByTestId(testId)
      .closest('.MuiAutocomplete-root')
      ?.querySelectorAll('.MuiChip-root') ?? []
  ).map(({ textContent }) => textContent);

const save = async (): Promise<void> => {
  const button = document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const openCreationForm = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  await userEvent.click(await screen.findByTestId('add-resource'));

  await userEvent.type(await getInput('host-template-form-name'), 'linux');
  await userEvent.type(await getInput('host-template-form-alias'), 'Linux');
};

const openTemplate = async (id: number): Promise<void> => {
  initialize({ search: `?mode=edit&id=${id}` });

  await waitForRequest(`@getHostTemplate${id}`);
};

export default () => {
  describe('Relations: ', () => {
    it('opens a template on its host categories and severity', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(testIds.categories)).toEqual(['Virtual'])
      );
      expect(screen.getByTestId(testIds.severity)).toHaveValue('Minor');
    });

    it('creates a template with categories and a severity read from the host template routes', async () => {
      await openCreationForm();

      await userEvent.click(screen.getByTestId(testIds.categories));
      const { request: categoriesRequest } = await waitForRequest(
        '@getFormHostCategories'
      );

      expect(categoriesRequest.url.pathname).toContain(
        '/api/configuration/host_templates/host_categories'
      );
      await pickOption('Physical');

      await userEvent.click(screen.getByTestId(testIds.severity));
      const { request: severitiesRequest } = await waitForRequest(
        '@getFormHostSeverities'
      );

      expect(severitiesRequest.url.pathname).toContain(
        '/api/configuration/host_templates/host_severities'
      );
      await pickOption('Critical');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(body.category_ids).toEqual([5]);
      expect(body.severity_id).toBe(1);
    });

    it('creates a template with no category and no severity', async () => {
      await openCreationForm();

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(body.category_ids).toEqual([]);
      expect(body.severity_id).toBeNull();
    });

    it('shows none of the relations only a host has', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(testIds.categories)).toEqual(['Virtual'])
      );
      expect(screen.queryByLabelText(labelHostGroups)).not.toBeInTheDocument();
      expect(screen.queryByLabelText(labelParentHosts)).not.toBeInTheDocument();
      expect(screen.queryByLabelText(labelChildHosts)).not.toBeInTheDocument();
    });

    it('opens a locked template with its relations read-only', async () => {
      await openTemplate(3);

      await waitFor(() =>
        expect(screen.getByTestId(testIds.severity)).toBeDisabled()
      );
      expect(screen.getByTestId(testIds.categories)).toBeDisabled();
    });
  });
};
