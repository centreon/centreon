import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelMassChange } from '../../ConfigurationBase/translatedLabels';
import initialize from './initialize';

const prefix = 'host-template-form';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');
};

const tickRow = async (id: number): Promise<void> => {
  await userEvent.click(screen.getByLabelText(`Select row ${id}`));
};

const openMassChange = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(listingDataTestIds.moreActions));
  await userEvent.click(
    within(
      (await screen.findAllByRole('menu')).at(-1) as HTMLElement
    ).getByText(labelMassChange)
  );
};

const getHeader = (): Promise<HTMLElement> =>
  screen.findByTestId(panelDataTestIds.header);

const save = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector(
      `button[data-testid="${panelDataTestIds.save}"]`
    ) as HTMLButtonElement
  );
};

const confirm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('confirm'));
};

const getMaxCheckAttempts = async (): Promise<HTMLInputElement> =>
  (
    await screen.findAllByTestId(
      `${prefix}-scheduling-options-maxCheckAttempts`
    )
  )[1] as HTMLInputElement;

const typeMaxCheckAttempts = async (value: string): Promise<void> => {
  await userEvent.type(await getMaxCheckAttempts(), value);
};

const expectConfirmation = async (text: string): Promise<void> => {
  expect(
    await screen.findByTestId('mass-change-confirmation')
  ).toHaveTextContent(new RegExp(`^${text}$`));
};

const pickOption = async (testId: string, option: string): Promise<void> => {
  await userEvent.click(screen.getByTestId(testId));
  await waitFor(() =>
    expect(document.querySelector('.MuiAutocomplete-popper')).not.toBeNull()
  );
  await userEvent.click(
    await within(
      document.querySelector('.MuiAutocomplete-popper') as HTMLElement
    ).findByText(option)
  );
};

// Exactly these templates were written to, once each, with this body when one
// is given.
const expectPatchedTemplates = async (
  expectedIds: Array<number>,
  body?: object
): Promise<void> => {
  await waitFor(() => {
    [1, 2, 3].forEach((id) => {
      const calls = getRequestCalls(`updateHostTemplate${id}`);

      expect(calls).toHaveLength(expectedIds.includes(id) ? 1 : 0);

      if (!expectedIds.includes(id)) {
        return;
      }

      const [{ answered, request }] = calls;

      expect(request.url.pathname).toMatch(
        new RegExp(`/api/configuration/host_templates/${id}$`)
      );

      if (body) {
        expect(request.body).toEqual(body);
      }

      expect(answered).toBe(true);
    });
  });
};

export default () => {
  describe('Mass change: ', () => {
    it('applies the changes to the templates ticked one by one, sending only what changed', async () => {
      await initializeListing();

      await tickRow(1);
      await tickRow(2);

      await openMassChange();

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (2)`);
      // Nothing that identifies one template, and no macros.
      await getMaxCheckAttempts();
      expect(screen.queryByTestId(`${prefix}-name`)).not.toBeInTheDocument();
      expect(screen.queryByTestId(`${prefix}-alias`)).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(`${prefix}-check-options-macros`)
      ).not.toBeInTheDocument();

      // Incremental, the default: added to each template's own.
      await pickOption(`${prefix}-linked-service-templates`, 'generic-ping');

      // Replacement with nothing picked clears the categories.
      await userEvent.click(
        screen.getByTestId(`${prefix}-update-mode-categories-replacement`)
      );

      await typeMaxCheckAttempts('3');

      await save();

      await expectConfirmation(
        'The changes will be applied to 2 host templates.'
      );

      await confirm();

      await expectPatchedTemplates([1, 2], {
        category_ids: [],
        linked_service_template_ids_to_add: [31],
        scheduling_options: { max_check_attempts: 3 }
      });

      expect(
        await screen.findByText('Host templates updated')
      ).toBeInTheDocument();
    });

    it('replaces the parent templates of every selected template', async () => {
      await initializeListing();

      await tickRow(1);
      await tickRow(2);
      await openMassChange();

      await userEvent.click(
        await screen.findByTestId(`${prefix}-update-mode-templates-replacement`)
      );

      await save();
      await confirm();

      await expectPatchedTemplates([1, 2], { template_ids: [] });
    });

    it('leaves the locked template out of a select-all mass change', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Select all'));
      await openMassChange();

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (2)`);

      await typeMaxCheckAttempts('3');
      await save();
      await confirm();

      await expectPatchedTemplates([1, 2], {
        scheduling_options: { max_check_attempts: 3 }
      });
    });

    it('writes nothing until the confirmation is accepted', async () => {
      await initializeListing();

      await tickRow(1);
      await openMassChange();

      await typeMaxCheckAttempts('3');
      await save();

      await userEvent.click(await screen.findByTestId('cancel'));

      expect(await getHeader()).toHaveTextContent(`${labelMassChange} (1)`);
      await waitFor(() =>
        expect(
          screen.queryByTestId('mass-change-confirmation')
        ).not.toBeInTheDocument()
      );
      await expectPatchedTemplates([]);

      await save();
      await expectConfirmation(
        'The changes will be applied to 1 host template.'
      );
      await confirm();

      expect(
        await screen.findByText('Host template updated')
      ).toBeInTheDocument();
      await expectPatchedTemplates([1], {
        scheduling_options: { max_check_attempts: 3 }
      });
    });
  });
};
