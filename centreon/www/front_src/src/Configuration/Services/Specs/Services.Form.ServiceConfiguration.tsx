import { Method } from '@centreon/ui';

import { interceptAPIRequest, waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { getServiceEndpoint, servicesListEndpoint } from '../api/endpoints';
import {
  serviceFormHostsEndpoint,
  serviceFormServiceTemplatesEndpoint,
  serviceFormTimePeriodsEndpoint
} from '../Form/sections/serviceConfiguration';
import {
  labelMustBeIntegerOfAtLeastOne,
  labelNameContainsForbiddenCharacters,
  labelRequired
} from '../translatedLabels';
import initialize from './initialize';
import {
  getServiceFormHostsResponse,
  getServiceFormServiceTemplatesResponse,
  getServiceFormTimePeriodsResponse
} from './utils';

interface RequestBody {
  host_id?: number | null;
  name?: string;
  scheduling_options: Record<string, unknown>;
  service_template_id: number | null;
}

// The section's own requests, kept here so `initialize.tsx` stays shared
// untouched: the selectors, and the create and update routes the form sends
// its body to (placeholders until the backend exposes them).
const interceptServiceConfigurationRequests = (): void => {
  interceptAPIRequest({
    alias: 'getServiceFormHosts',
    method: Method.GET,
    path: `**${serviceFormHostsEndpoint}?**`,
    response: getServiceFormHostsResponse()
  });
  interceptAPIRequest({
    alias: 'getServiceFormServiceTemplates',
    method: Method.GET,
    path: `**${serviceFormServiceTemplatesEndpoint}?**`,
    response: getServiceFormServiceTemplatesResponse()
  });
  interceptAPIRequest({
    alias: 'getServiceFormTimePeriods',
    method: Method.GET,
    path: `**${serviceFormTimePeriodsEndpoint}?**`,
    response: getServiceFormTimePeriodsResponse()
  });
  interceptAPIRequest({
    alias: 'createService',
    method: Method.POST,
    path: `**${servicesListEndpoint}`,
    response: { id: 12, name: 'new service' }
  });
  interceptAPIRequest({
    alias: 'updateService0',
    method: Method.PUT,
    path: `**${getServiceEndpoint({ id: 0 })}`,
    response: {}
  });
};

const openNewService = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);
  interceptServiceConfigurationRequests();

  await waitForRequest('@getAllServices');

  await userEvent.click(await screen.findByTestId('add-resource'));
};

const openService = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);
  interceptServiceConfigurationRequests();

  await waitForRequest('@getAllServices');

  await userEvent.click(await screen.findByText('cpu'));

  await waitForRequest('@getService0');
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
  it('opens a service on its name, host, template and scheduling', async () => {
    await openService();

    await waitFor(async () =>
      expect(await getInput('service-form-name')).toHaveValue('cpu')
    );
    expect(screen.getByTestId('service-form-host')).toHaveValue('host A');
    expect(screen.getByTestId('service-form-template')).toHaveValue(
      'generic-active-service'
    );
    expect(screen.getByTestId('service-form-check-period')).toHaveValue(
      'workhours'
    );
    expect(await getInput('service-form-max-check-attempts')).toHaveValue(3);
    expect(await getInput('service-form-normal-check-interval')).toHaveValue(5);
    expect(await getInput('service-form-retry-check-interval')).toHaveValue(
      null
    );
    expect(
      screen.getByTestId('service-form-active-checks-enabled-false')
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      screen.getByTestId('service-form-passive-checks-enabled-use_default')
    ).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByTestId('service-form-is-volatile-true')).toHaveAttribute(
      'aria-pressed',
      'true'
    );
  });

  it('opens a service on its enable switch, which the form leaves out', async () => {
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

    expect(body).not.toHaveProperty('activated');
    expect(body).toMatchObject({
      host_id: 10,
      name: 'cpu load',
      service_template_id: 30
    });
    expect(body.scheduling_options).toEqual({
      active_check_enabled: 'false',
      check_timeperiod_id: 21,
      is_volatile: 'true',
      max_check_attempts: 3,
      normal_check_interval: 5,
      passive_check_enabled: 'use_default',
      retry_check_interval: null
    });
  });

  it('creates a service with its host, template and scheduling', async () => {
    await openNewService();

    // Empty, even though the page could guess one.
    await waitFor(() =>
      expect(screen.getByTestId('service-form-host')).toHaveValue('')
    );
    expect(
      screen.getByTestId('service-form-is-volatile-use_default')
    ).toHaveAttribute('aria-pressed', 'true');

    await userEvent.type(await getInput('service-form-name'), '  cpu   load ');
    await pickOption('service-form-host', 'host B');

    const { request: hostsRequest } = await waitForRequest(
      '@getServiceFormHosts'
    );

    expect(hostsRequest.url.pathname).toContain(
      '/api/configuration/services/hosts'
    );
    expect(hostsRequest.url.pathname).not.toContain('/api/latest');

    await pickOption('service-form-template', 'generic-passive-service');
    await pickOption('service-form-check-period', '24x7');
    await userEvent.type(
      await getInput('service-form-max-check-attempts'),
      '4'
    );
    await userEvent.type(
      await getInput('service-form-normal-check-interval'),
      '10'
    );
    await userEvent.click(
      screen.getByTestId('service-form-active-checks-enabled-true')
    );
    await userEvent.click(
      screen.getByTestId('service-form-passive-checks-enabled-false')
    );
    await userEvent.click(screen.getByTestId('service-form-is-volatile-true'));

    await save();

    const body = await getRequestBody('@createService');

    expect(body).toMatchObject({
      host_id: 11,
      // Trimmed, inner spaces collapsed, as the server stores it.
      name: 'cpu load',
      service_template_id: 31
    });
    expect(body.scheduling_options).toEqual({
      active_check_enabled: 'true',
      check_timeperiod_id: 20,
      is_volatile: 'true',
      max_check_attempts: 4,
      normal_check_interval: 10,
      passive_check_enabled: 'false',
      retry_check_interval: null
    });
  });

  it('refuses to save a service without a name or a host', async () => {
    await openNewService();

    await waitFor(() => expect(getSaveButton()).toBeDisabled());

    const name = await getInput('service-form-name');

    leave(name);
    expect(await getFieldError(name, labelRequired)).toBeVisible();

    await userEvent.type(name, 'cpu');

    const host = await screen.findByTestId('service-form-host');

    leave(host);
    expect(await getFieldError(host, labelRequired)).toBeVisible();
    expect(getSaveButton()).toBeDisabled();
  });

  it('refuses a name with a character the engine forbids', async () => {
    await openNewService();

    await userEvent.type(await getInput('service-form-name'), 'cpu (load)');
    await userEvent.tab();

    expect(
      await screen.findByText(labelNameContainsForbiddenCharacters)
    ).toBeVisible();
  });

  it('refuses a check attempt count below 1', async () => {
    await openNewService();

    const maxCheckAttempts = await getInput('service-form-max-check-attempts');

    await userEvent.type(maxCheckAttempts, '0');
    leave(maxCheckAttempts);

    expect(
      await screen.findByText(labelMustBeIntegerOfAtLeastOne)
    ).toBeVisible();
  });

  it('lets a user who may only look at services change none of these fields', async () => {
    await openService({ hasWriteAccess: false });

    await waitFor(async () =>
      expect(await getInput('service-form-name')).toBeDisabled()
    );
    expect(screen.getByTestId('service-form-host')).toBeDisabled();
    expect(screen.getByTestId('service-form-template')).toBeDisabled();
    expect(screen.getByTestId('service-form-check-period')).toBeDisabled();
    expect(await getInput('service-form-normal-check-interval')).toBeDisabled();
    expect(screen.getByTestId('service-form-is-volatile-true')).toBeDisabled();
  });

  it('offers no check toggles and requires a template on a cloud platform', async () => {
    await openNewService({ isCloudPlatform: true });

    expect(await getInput('service-form-max-check-attempts')).toBeVisible();
    expect(
      screen.queryByTestId('service-form-active-checks-enabled')
    ).not.toBeInTheDocument();
    expect(
      screen.queryByTestId('service-form-passive-checks-enabled')
    ).not.toBeInTheDocument();
    expect(
      screen.queryByTestId('service-form-is-volatile')
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

    expect(body.scheduling_options).toEqual({
      check_timeperiod_id: null,
      max_check_attempts: null,
      normal_check_interval: null,
      retry_check_interval: null
    });
  });
};
