import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelMassChange,
  labelMoreActions
} from '../../ConfigurationBase/translatedLabels';
import initialize, { patchedTemplateIds } from './initialize';

const initializeListing = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('Generic active host');
};

const openMassChange = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(labelMoreActions));
  await userEvent.click(
    within(
      (await screen.findAllByRole('menu')).at(-1) as HTMLElement
    ).getByText(labelMassChange)
  );
};

const save = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector(
      `button[data-testid="${panelDataTestIds.save}"]`
    ) as HTMLButtonElement
  );
};

const pickOption = async (label: string): Promise<void> => {
  await waitFor(() =>
    expect(document.querySelector('.MuiAutocomplete-popper')).not.toBeNull()
  );
  await userEvent.click(
    await within(
      document.querySelector('.MuiAutocomplete-popper') as HTMLElement
    ).findByText(label)
  );
};

export default () => {
  describe('Mass change: ', () => {
    it('shows the template lists with their update mode, and nothing only a host has', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Select row 5'));
      await openMassChange();

      expect(
        await screen.findByTestId(panelDataTestIds.header)
      ).toHaveTextContent(`${labelMassChange} (1)`);

      for (const field of ['serviceTemplates', 'templates', 'categories']) {
        expect(
          await screen.findByTestId(
            `host-form-update-mode-${field}-replacement`
          )
        ).toBeInTheDocument();
      }
      expect(
        screen.queryByTestId('host-form-update-mode-groups-replacement')
      ).not.toBeInTheDocument();

      [
        'host-form-name',
        'host-form-poller',
        'host-form-groups',
        'host-form-parent-hosts',
        'host-form-child-hosts'
      ].forEach((testId) => {
        expect(screen.queryByTestId(testId)).not.toBeInTheDocument();
      });
    });

    it('adds the linked service templates to every selected template', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Select row 5'));
      await userEvent.click(screen.getByLabelText('Select row 6'));
      await openMassChange();

      // Incremental, the default: added to each template's own.
      await userEvent.click(
        await screen.findByTestId('host-form-service-templates')
      );
      await pickOption('Base-Ping-LAN');

      await save();

      // No warning: replacing a template's parents deletes no service.
      expect(
        await screen.findByTestId('mass-change-confirmation')
      ).toHaveTextContent('2 host templates');
      expect(
        screen.queryByTestId('mass-change-warning')
      ).not.toBeInTheDocument();

      await userEvent.click(await screen.findByTestId('confirm'));

      await waitFor(() => {
        expect([...patchedTemplateIds].sort()).toEqual([5, 6]);
      });

      [5, 6].forEach((id) => {
        const [{ request }] = getRequestCalls(`patchHostTemplate${id}`);

        expect(request.url.pathname).toMatch(
          new RegExp(`/api/configuration/host_templates/${id}$`)
        );
        expect(request.body).toEqual({ service_template_ids_to_add: [31] });
      });
    });
  });
};
