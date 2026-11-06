import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelMustBeIntegerOfAtLeastOne,
  labelRequired
} from '../../ResourceForm/translatedLabels';
import initialize from './initialize';

interface RequestBody {
  host_id?: number | null;
  name?: string;
  monitoring_engine: Record<string, unknown>;
  service_template_id?: number | null;
}

const schedulingTestId = (field: string): string =>
  `service-form-scheduling-options-${field}`;

const openListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
};

const openNewService = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  await openListing(props);

  await userEvent.click(await screen.findByTestId('add-resource'));
};

const openService = async (
  props: Parameters<typeof initialize>[0] = {},
  { id, name }: { id: number; name: string } = { id: 0, name: 'cpu' }
): Promise<void> => {
  await openListing(props);

  await userEvent.click(await screen.findByText(name));

  await waitForRequest(`@getService${id}`);
};

// The keys the services API takes on a create and an update: a form value
// sent under its own name, or a group the host API spells otherwise, is not
// one of them.
const createServiceKeys = [
  'advanced',
  'category_ids',
  'check_options',
  'graph_template_id',
  'host_id',
  'monitoring_engine',
  'name',
  'notifications',
  'service_group_ids',
  'service_template_id',
  'severity_id',
  'trap_ids'
];
const updateServiceKeys = [...createServiceKeys, 'activated'];

const expectOnlyApiKeys = (body: RequestBody, apiKeys: Array<string>): void => {
  expect(Object.keys(body).filter((key) => !apiKeys.includes(key))).toEqual([]);
};

const clearTemplate = async (): Promise<void> => {
  // The clear indicator only shows on hover; userEvent clicks it anyway.
  await userEvent.click(
    screen
      .getByTestId('service-form-template')
      .closest('.MuiAutocomplete-root')
      ?.querySelector('.MuiAutocomplete-clearIndicator') as HTMLElement
  );
};

const defaultScheduling = {
  active_check_enabled: 'use_default',
  check_timeperiod_id: null,
  is_volatile: 'use_default',
  max_check_attempts: null,
  normal_check_interval: null,
  passive_check_enabled: 'use_default',
  retry_check_interval: null
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const pickOption = async (testId: string, option: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(testId));
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
  await waitFor(() => expect(screen.getByTestId(testId)).toHaveValue(option));
};

const getSaveButton = (): HTMLButtonElement =>
  document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton());
};

const leave = (element: HTMLElement): void => {
  act(() => {
    element.focus();
    element.blur();
  });
};

const getFieldError = (element: HTMLElement, message: string) =>
  within(element.closest('.MuiFormControl-root') as HTMLElement).findByText(
    message
  );

const getRequestBody = async (alias: string): Promise<RequestBody> => {
  const { request } = await waitForRequest(alias);

  return request.body as RequestBody;
};

export default () => {
  describe('Template and scheduling: ', () => {
    it('opens a service on its name, host, template and scheduling', async () => {
      await openService();

      await waitFor(async () =>
        expect(await getInput('service-form-name')).toHaveValue('cpu')
      );
      expect(screen.getByTestId('service-form-host')).toHaveValue('host A');
      expect(screen.getByTestId('service-form-template')).toHaveValue(
        'generic-active-service'
      );
      expect(screen.getByTestId(schedulingTestId('checkPeriod'))).toHaveValue(
        'workhours'
      );
      expect(await getInput(schedulingTestId('maxCheckAttempts'))).toHaveValue(
        3
      );
      expect(
        await getInput(schedulingTestId('normalCheckInterval'))
      ).toHaveValue(5);
      expect(
        await getInput(schedulingTestId('retryCheckInterval'))
      ).toHaveValue(null);
      expect(
        screen.getByTestId(schedulingTestId('activeCheckEnabled-false'))
      ).toHaveAttribute('aria-pressed', 'true');
      expect(
        screen.getByTestId(schedulingTestId('passiveCheckEnabled-use_default'))
      ).toHaveAttribute('aria-pressed', 'true');
      expect(
        screen.getByTestId(schedulingTestId('isVolatile-true'))
      ).toHaveAttribute('aria-pressed', 'true');
    });

    it('opens a service on its enable switch, whose state the form sends back', async () => {
      await openService();

      expect(
        await screen.findByTestId(panelDataTestIds.enable)
      ).toBeInTheDocument();

      const name = await getInput('service-form-name');

      await waitFor(() => expect(name).toHaveValue('cpu'));
      await userEvent.clear(name);
      await userEvent.type(name, 'cpu load');

      await save();

      const body = await getRequestBody('@updateService0');

      // The update replaces the whole service, its activation included.
      expectOnlyApiKeys(body, updateServiceKeys);
      expect(body).toMatchObject({
        activated: true,
        host_id: 10,
        name: 'cpu load',
        service_template_id: 30
      });
      expect(body.monitoring_engine).toEqual({
        active_check_enabled: 'false',
        check_timeperiod_id: 21,
        is_volatile: 'true',
        max_check_attempts: 3,
        normal_check_interval: 5,
        passive_check_enabled: 'use_default',
        retry_check_interval: null
      });
    });

    it('opens a service with no template and the default scheduling', async () => {
      await openService({}, { id: 1, name: 'memory' });

      const name = await getInput('service-form-name');

      await waitFor(() => expect(name).toHaveValue('memory'));
      expect(screen.getByTestId('service-form-template')).toHaveValue('');
      expect(screen.getByTestId(schedulingTestId('checkPeriod'))).toHaveValue(
        ''
      );
      expect(await getInput(schedulingTestId('maxCheckAttempts'))).toHaveValue(
        null
      );
      ['activeCheckEnabled', 'passiveCheckEnabled', 'isVolatile'].forEach(
        (field) => {
          expect(
            screen.getByTestId(schedulingTestId(`${field}-use_default`))
          ).toHaveAttribute('aria-pressed', 'true');
        }
      );

      await userEvent.type(name, ' usage');

      await save();

      const body = await getRequestBody('@updateService1');

      expect(body.service_template_id).toBeNull();
      expect(body.monitoring_engine).toEqual(defaultScheduling);
    });

    it('removes the template of a service', async () => {
      await openService();

      await waitFor(() =>
        expect(screen.getByTestId('service-form-template')).toHaveValue(
          'generic-active-service'
        )
      );

      await clearTemplate();
      await waitFor(() =>
        expect(screen.getByTestId('service-form-template')).toHaveValue('')
      );

      await save();

      const body = await getRequestBody('@updateService0');

      expect(body.service_template_id).toBeNull();
    });

    it('shows the enable switch on a deep link, from the detail', async () => {
      initialize({ searchParams: '?mode=edit&id=0' });

      await waitForRequest('@getService0');

      expect(
        await screen.findByTestId(panelDataTestIds.enable)
      ).toBeInTheDocument();
    });

    it('creates a service with its host, template and scheduling', async () => {
      await openNewService();

      await waitFor(() =>
        expect(screen.getByTestId('service-form-host')).toHaveValue('')
      );
      expect(screen.getByTestId('service-form-template')).toHaveValue('');
      expect(
        screen.getByTestId(schedulingTestId('isVolatile-use_default'))
      ).toHaveAttribute('aria-pressed', 'true');

      await userEvent.type(await getInput('service-form-name'), 'cpu');
      await pickOption('service-form-host', 'host B');
      await pickOption('service-form-template', 'generic-passive-service');
      await pickOption(schedulingTestId('checkPeriod'), '24x7');

      const { request: timePeriodsRequest } = await waitForRequest(
        '@getServiceFormTimePeriods'
      );

      expect(timePeriodsRequest.url.pathname).toContain(
        '/api/configuration/services/timeperiods'
      );

      await userEvent.type(
        await getInput(schedulingTestId('maxCheckAttempts')),
        '4'
      );
      await userEvent.type(
        await getInput(schedulingTestId('normalCheckInterval')),
        '10'
      );
      await userEvent.click(
        screen.getByTestId(schedulingTestId('activeCheckEnabled-true'))
      );
      await userEvent.click(
        screen.getByTestId(schedulingTestId('passiveCheckEnabled-false'))
      );
      await userEvent.click(
        screen.getByTestId(schedulingTestId('isVolatile-true'))
      );

      await save();

      const body = await getRequestBody('@createService');

      expect(body).not.toHaveProperty('activated');
      expect(body).toMatchObject({
        host_id: 11,
        name: 'cpu',
        service_template_id: 31
      });
      expect(body.monitoring_engine).toEqual({
        active_check_enabled: 'true',
        check_timeperiod_id: 20,
        is_volatile: 'true',
        max_check_attempts: 4,
        normal_check_interval: 10,
        passive_check_enabled: 'false',
        retry_check_interval: null
      });
    });

    it('sends no template and the default scheduling when left empty', async () => {
      await openNewService();

      await userEvent.type(await getInput('service-form-name'), 'cpu');
      await pickOption('service-form-host', 'host A');

      await save();

      const body = await getRequestBody('@createService');

      expect(body.service_template_id).toBeNull();
      expect(body.monitoring_engine).toEqual(defaultScheduling);
    });

    it('refuses a check attempt count below 1', async () => {
      await openNewService();

      const maxCheckAttempts = await getInput(
        schedulingTestId('maxCheckAttempts')
      );

      await userEvent.type(maxCheckAttempts, '0');
      leave(maxCheckAttempts);

      expect(
        await getFieldError(maxCheckAttempts, labelMustBeIntegerOfAtLeastOne)
      ).toBeVisible();
    });

    it('lets a user who may only look at services change none of these fields', async () => {
      await openService({ hasWriteAccess: false });

      await waitFor(() =>
        expect(screen.getByTestId('service-form-template')).toBeDisabled()
      );
      expect(
        screen.getByTestId(schedulingTestId('checkPeriod'))
      ).toBeDisabled();
      expect(
        await getInput(schedulingTestId('normalCheckInterval'))
      ).toBeDisabled();
      expect(
        screen.getByTestId(schedulingTestId('isVolatile-true'))
      ).toBeDisabled();
    });

    it('offers no check toggles and requires a template on a cloud platform', async () => {
      await openNewService({ isCloudPlatform: true });

      expect(
        await getInput(schedulingTestId('maxCheckAttempts'))
      ).toBeVisible();
      expect(
        screen.queryByTestId(schedulingTestId('activeCheckEnabled'))
      ).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(schedulingTestId('passiveCheckEnabled'))
      ).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(schedulingTestId('isVolatile'))
      ).not.toBeInTheDocument();

      await userEvent.type(await getInput('service-form-name'), 'cpu');
      await pickOption('service-form-host', 'host A');

      const template = screen.getByTestId('service-form-template');

      leave(template);
      expect(await getFieldError(template, labelRequired)).toBeVisible();
      expect(getSaveButton()).toBeDisabled();

      await pickOption('service-form-template', 'generic-active-service');

      await save();

      const body = await getRequestBody('@createService');

      expectOnlyApiKeys(body, createServiceKeys);
      expect(body.service_template_id).toBe(30);
      expect(body.monitoring_engine).toEqual({
        check_timeperiod_id: null,
        max_check_attempts: null,
        normal_check_interval: null,
        retry_check_interval: null
      });
    });
  });
};
