import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelGeographicCoordinates } from '../../ResourceForm/translatedLabels';
import initialize from './initialize';

const prefix = 'host-template-form';

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

const save = async (): Promise<void> => {
  const button = document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const openCreationForm = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  await userEvent.click(await screen.findByTestId('add-resource'));

  await userEvent.type(await getInput(`${prefix}-name`), 'linux');
  await userEvent.type(await getInput(`${prefix}-alias`), 'Linux');
};

const openTemplate = async (id: number): Promise<void> => {
  initialize({ search: `?mode=edit&id=${id}` });

  await waitForRequest(`@getHostTemplate${id}`);
};

const getCreateBody = async (): Promise<Record<string, unknown>> => {
  await save();

  const { request } = await waitForRequest('@createHostTemplate');

  return request.body as Record<string, unknown>;
};

export default () => {
  describe('Monitoring engine / Scheduling options: ', () => {
    it('opens a template on its host details and scheduling options', async () => {
      await openTemplate(1);

      await waitFor(async () =>
        expect(await getInput(`${prefix}-extended-infos-note`)).toHaveValue(
          'Front web servers'
        )
      );
      expect(screen.getByTestId(`${prefix}-extended-infos-icon`)).toHaveValue(
        'server.png'
      );
      expect(await getInput(`${prefix}-extended-infos-actionUrl`)).toHaveValue(
        'https://example.com/actions'
      );
      expect(screen.getByTestId(`${prefix}-timezone`)).toHaveValue(
        'Europe/Paris'
      );
      // Above the timezone, as designed.
      expect(
        screen
          .getAllByTestId(`${prefix}-extended-infos-actionUrl`)[0]
          .getBoundingClientRect().bottom
      ).toBeLessThanOrEqual(
        screen.getByTestId(`${prefix}-timezone`).getBoundingClientRect().top
      );
      expect(
        screen.getByTestId(`${prefix}-scheduling-options-checkPeriod`)
      ).toHaveValue('workhours');
      expect(
        await getInput(`${prefix}-scheduling-options-maxCheckAttempts`)
      ).toHaveValue(3);
      expect(
        await getInput(`${prefix}-scheduling-options-normalCheckInterval`)
      ).toHaveValue(5);
      expect(
        screen.getByTestId(
          `${prefix}-scheduling-options-activeCheckEnabled-false`
        )
      ).toHaveAttribute('aria-pressed', 'true');
      expect(
        screen.getByTestId(
          `${prefix}-scheduling-options-passiveCheckEnabled-true`
        )
      ).toHaveAttribute('aria-pressed', 'true');
    });

    it('shows no geographic coordinates', async () => {
      await openTemplate(1);

      await getInput(`${prefix}-extended-infos-note`);

      expect(
        screen.queryByLabelText(labelGeographicCoordinates)
      ).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(`${prefix}-extended-infos-geoCoordinates`)
      ).not.toBeInTheDocument();
    });

    it('creates a template with host details and scheduling options read from the host template routes', async () => {
      await openCreationForm();

      await userEvent.type(
        await getInput(`${prefix}-extended-infos-note`),
        '  Routers  '
      );
      await userEvent.type(
        await getInput(`${prefix}-extended-infos-altIcon`),
        'Router'
      );
      await userEvent.type(
        await getInput(`${prefix}-extended-infos-actionUrl`),
        'https://example.com/act'
      );

      await userEvent.click(
        screen.getByTestId(`${prefix}-extended-infos-icon`)
      );
      const { request: mediasRequest } = await waitForRequest('@getFormMedias');

      expect(mediasRequest.url.pathname).toContain(
        '/api/configuration/host_templates/medias'
      );
      await pickOption('router.png');

      await userEvent.click(screen.getByTestId(`${prefix}-timezone`));
      const { request: timezonesRequest } =
        await waitForRequest('@getFormTimezones');

      expect(timezonesRequest.url.pathname).toContain(
        '/api/configuration/timezones'
      );
      await pickOption('Europe/London');

      await userEvent.click(
        screen.getByTestId(`${prefix}-scheduling-options-checkPeriod`)
      );
      const { request: timePeriodsRequest } = await waitForRequest(
        '@getFormTimePeriods'
      );

      expect(timePeriodsRequest.url.pathname).toContain(
        '/api/configuration/host_templates/timeperiods'
      );
      await pickOption('24x7');

      await userEvent.type(
        await getInput(`${prefix}-scheduling-options-maxCheckAttempts`),
        '4'
      );
      await userEvent.click(
        screen.getByTestId(
          `${prefix}-scheduling-options-activeCheckEnabled-true`
        )
      );

      const body = await getCreateBody();

      expect(body.extended_informations).toEqual({
        action_url: 'https://example.com/act',
        alt_icon: 'Router',
        icon_id: 13,
        note: 'Routers',
        note_url: null
      });
      expect(body.extended_informations).not.toHaveProperty('geo_coordinates');
      expect(body.timezone_id).toBe(1);
      expect(body.scheduling_options).toEqual({
        active_check_enabled: 'true',
        check_timeperiod_id: 1,
        max_check_attempts: 4,
        normal_check_interval: null,
        passive_check_enabled: 'use_default',
        retry_check_interval: null
      });
    });

    it('sends neither the alternative icon nor active and passive checks on a cloud platform', async () => {
      await openCreationForm({ isCloudPlatform: true });

      expect(
        screen.queryByTestId(`${prefix}-extended-infos-altIcon`)
      ).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(`${prefix}-scheduling-options-activeCheckEnabled`)
      ).not.toBeInTheDocument();

      const body = await getCreateBody();

      expect(body.extended_informations).toEqual({
        action_url: null,
        icon_id: null,
        note: null,
        note_url: null
      });
      expect(body.scheduling_options).toEqual({
        check_timeperiod_id: null,
        max_check_attempts: null,
        normal_check_interval: null,
        retry_check_interval: null
      });
    });

    it('opens a template with its host details and scheduling options read-only for a user who may only look at templates', async () => {
      initialize({ hasWriteAccess: false, search: '?mode=edit&id=1' });

      await waitForRequest('@getHostTemplate1');

      await waitFor(async () =>
        expect(await getInput(`${prefix}-extended-infos-note`)).toHaveValue(
          'Front web servers'
        )
      );
      for (const field of ['note', 'noteUrl', 'altIcon', 'actionUrl']) {
        expect(
          await getInput(`${prefix}-extended-infos-${field}`)
        ).toBeDisabled();
      }
      for (const testId of [
        `${prefix}-extended-infos-icon`,
        `${prefix}-timezone`,
        `${prefix}-scheduling-options-checkPeriod`
      ]) {
        expect(screen.getByTestId(testId)).toBeDisabled();
      }
      for (const field of [
        'maxCheckAttempts',
        'normalCheckInterval',
        'retryCheckInterval'
      ]) {
        expect(
          await getInput(`${prefix}-scheduling-options-${field}`)
        ).toBeDisabled();
      }
      for (const field of ['activeCheckEnabled', 'passiveCheckEnabled']) {
        const buttons = within(
          screen.getByTestId(`${prefix}-scheduling-options-${field}`)
        ).getAllByRole('button');

        expect(buttons).toHaveLength(3);
        buttons.forEach((button) => {
          expect(button).toBeDisabled();
        });
      }
    });

    it('opens a locked template with its host details and scheduling options read-only', async () => {
      await openTemplate(3);

      await waitFor(async () =>
        expect(await getInput(`${prefix}-extended-infos-note`)).toBeDisabled()
      );
      expect(screen.getByTestId(`${prefix}-timezone`)).toBeDisabled();
      expect(
        await getInput(`${prefix}-scheduling-options-maxCheckAttempts`)
      ).toBeDisabled();
    });
  });
};
