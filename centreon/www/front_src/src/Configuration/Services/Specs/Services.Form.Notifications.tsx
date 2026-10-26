import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelDefault, labelNo, labelYes } from '../../Hosts/translatedLabels';
import {
  labelCritical,
  labelDowntimeScheduled,
  labelFlapping,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelMustBePositiveIntegerOrZero,
  labelNone,
  labelRecovery,
  labelUnknown,
  labelWarning
} from '../translatedLabels';
import { fillRequiredServiceFields } from './formHelpers';
import initialize, { interceptNotificationsRequests } from './initialize';
import { untouchedNotificationsPayload } from './utils';

interface RequestBody {
  notifications: Record<string, unknown>;
}

const testId = (field: string): string => `service-form-notifications-${field}`;

// `@centreon/ui` text fields put the same test id on the wrapper and the
// input: the input is the second one.
const getInput = async (field: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId(field)))[1] as HTMLInputElement;

const getOption = (label: string): HTMLElement =>
  screen.getByTestId(`${testId('options')}-${label}`);

const getSwitch = (field: string): HTMLInputElement =>
  screen.getByTestId(testId(field)).querySelector('input') as HTMLInputElement;

const getSaveButton = (): HTMLButtonElement =>
  document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

// A click before the form has validated what was typed last is lost.
const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton());
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: a name can also appear in the listing behind it.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const getChips = (label: string): NodeListOf<Element> =>
  (
    screen.getByLabelText(label).closest('.MuiAutocomplete-root') as Element
  ).querySelectorAll('.MuiChip-root');

const expectSingleChip = async (label: string, chip: string): Promise<void> => {
  await waitFor(() => {
    const chips = getChips(label);

    expect(chips).toHaveLength(1);
    expect(chips[0]).toHaveTextContent(new RegExp(`^${chip}$`));
  });
};

const clickEnabled = async (label: string): Promise<void> => {
  await userEvent.click(
    within(screen.getByTestId(testId('enabled'))).getByRole('button', {
      name: label
    })
  );
};

const expectEnabled = async (label: string): Promise<void> => {
  await waitFor(() =>
    expect(
      within(screen.getByTestId(testId('enabled'))).getByRole('button', {
        name: label
      })
    ).toHaveAttribute('aria-pressed', 'true')
  );
};

const openNewService = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);
  interceptNotificationsRequests();

  await waitForRequest('@getAllServices');

  await userEvent.click(await screen.findByTestId('add-resource'));
  await screen.findByTestId(testId('enabled'));
  // Without a name and a host the form cannot be saved.
  await fillRequiredServiceFields();
};

const openService = async (
  props: Parameters<typeof initialize>[0] = {},
  { id, name }: { id: number; name: string } = { id: 0, name: 'cpu' }
): Promise<void> => {
  initialize(props);
  interceptNotificationsRequests();

  await waitForRequest('@getAllServices');

  await userEvent.click(await screen.findByText(name));

  await waitForRequest(`@getService${id}`);
};

const getRequestBody = async (alias: string): Promise<RequestBody> => {
  const { request } = await waitForRequest(alias);

  return request.body as RequestBody;
};

export default () => {
  it('opens an existing service on its notification settings', async () => {
    await openService({ isAdditiveInheritanceEnabled: true });

    await expectEnabled(labelNo);

    await expectSingleChip(labelLinkedContacts, 'admin');
    await expectSingleChip(labelLinkedContactGroups, 'Supervisors');

    expect(await getInput('interval')).toHaveValue(3);
    expect(await getInput('firstDelay')).toHaveValue(1);
    // Left out of the response, so still empty.
    expect(await getInput('recoveryDelay')).toHaveValue(null);
    expect(screen.getByTestId(testId('timeperiod'))).toHaveValue('24x7');

    expect(getOption(labelWarning)).toHaveAttribute('aria-pressed', 'true');
    expect(getOption(labelCritical)).toHaveAttribute('aria-pressed', 'true');
    expect(getOption(labelRecovery)).toHaveAttribute('aria-pressed', 'true');
    expect(getOption(labelUnknown)).toHaveAttribute('aria-pressed', 'false');

    expect(getSwitch('use-only-contacts-from-host')).not.toBeChecked();
    expect(getSwitch('contact-additive-inheritance')).toBeChecked();
    expect(getSwitch('contact-group-additive-inheritance')).not.toBeChecked();
  });

  it('opens a service with no notification settings on their defaults', async () => {
    await openService({}, { id: 1, name: 'memory' });

    await expectEnabled(labelDefault);

    await clickEnabled(labelYes);

    await save();

    const body = await getRequestBody('@updateService1');

    expect(body.notifications).toEqual({
      ...untouchedNotificationsPayload,
      enabled: 'true'
    });
  });

  it('creates a service with its notification settings', async () => {
    await openNewService();

    await clickEnabled(labelYes);

    await userEvent.click(screen.getByTestId(testId('contacts')));
    const { request: contactsRequest } =
      await waitForRequest('@getFormContacts');

    expect(contactsRequest.url.pathname).toContain(
      '/api/configuration/services/contacts'
    );
    await pickOption('admin');
    // A multi-select stays open after a pick.
    await userEvent.keyboard('{Escape}');

    await userEvent.click(screen.getByTestId(testId('contact-groups')));
    const { request: contactGroupsRequest } = await waitForRequest(
      '@getFormContactGroups'
    );

    expect(contactGroupsRequest.url.pathname).toContain(
      '/api/configuration/services/contact_groups'
    );
    await pickOption('Supervisors');
    await userEvent.keyboard('{Escape}');

    await userEvent.type(await getInput('interval'), '5');

    await userEvent.click(screen.getByTestId(testId('timeperiod')));
    const { request: timePeriodsRequest } = await waitForRequest(
      '@getFormTimePeriods'
    );

    expect(timePeriodsRequest.url.pathname).toContain(
      '/api/configuration/services/timeperiods'
    );
    await pickOption('workhours');

    for (const option of [
      labelWarning,
      labelUnknown,
      labelFlapping,
      labelDowntimeScheduled
    ]) {
      await userEvent.click(getOption(option));
    }

    await userEvent.type(await getInput('firstDelay'), '0');
    await userEvent.type(await getInput('recoveryDelay'), '2');

    await save();

    const body = await getRequestBody('@createService');

    // No additive inheritance: the platform does not use it.
    expect(body.notifications).toEqual({
      contact_groups: [3],
      contacts: [1],
      enabled: 'true',
      first_delay: 0,
      interval: 5,
      options: ['warning', 'unknown', 'flapping', 'downtime_scheduled'],
      recovery_delay: 2,
      timeperiod_id: 2,
      use_only_contacts_from_host: false
    });
  });

  it('sends None alone once it is chosen', async () => {
    await openNewService();

    await userEvent.click(getOption(labelWarning));
    await userEvent.click(getOption(labelCritical));
    await userEvent.click(screen.getByLabelText(labelNone));

    await waitFor(() =>
      expect(getOption(labelWarning)).toHaveAttribute('aria-pressed', 'false')
    );

    await save();

    const body = await getRequestBody('@createService');

    expect(body.notifications.options).toEqual(['none']);
  });

  it('clears and disables the service recipients once only the host ones are used', async () => {
    await openService({ isAdditiveInheritanceEnabled: true });

    await expectSingleChip(labelLinkedContacts, 'admin');

    await userEvent.click(getSwitch('use-only-contacts-from-host'));

    await waitFor(() => expect(getChips(labelLinkedContacts)).toHaveLength(0));
    expect(getChips(labelLinkedContactGroups)).toHaveLength(0);
    expect(screen.getByTestId(testId('contacts'))).toBeDisabled();
    expect(screen.getByTestId(testId('contact-groups'))).toBeDisabled();
    expect(getSwitch('contact-additive-inheritance')).not.toBeChecked();
    expect(getSwitch('contact-additive-inheritance')).toBeDisabled();
    expect(getSwitch('contact-group-additive-inheritance')).toBeDisabled();

    await save();

    const body = await getRequestBody('@updateService0');

    expect(body.notifications).toMatchObject({
      contact_additive_inheritance: false,
      contact_group_additive_inheritance: false,
      contact_groups: [],
      contacts: [],
      use_only_contacts_from_host: true
    });
  });

  it('hides the additive inheritance toggles the platform does not use', async () => {
    await openNewService();

    expect(
      screen.queryByTestId(testId('contact-additive-inheritance'))
    ).not.toBeInTheDocument();
    expect(
      screen.queryByTestId(testId('contact-group-additive-inheritance'))
    ).not.toBeInTheDocument();
  });

  it('sends the additive inheritance toggles where the platform uses them', async () => {
    await openNewService({ isAdditiveInheritanceEnabled: true });

    await userEvent.click(getSwitch('contact-group-additive-inheritance'));

    await save();

    const body = await getRequestBody('@createService');

    expect(body.notifications).toEqual({
      ...untouchedNotificationsPayload,
      contact_additive_inheritance: false,
      contact_group_additive_inheritance: true
    });
  });

  it('refuses a negative notification interval or delay', async () => {
    await openNewService();

    await userEvent.type(await getInput('interval'), '-1');
    const recoveryDelay = await getInput('recoveryDelay');

    await userEvent.type(recoveryDelay, '-3');
    act(() => {
      recoveryDelay.blur();
    });

    // Each message shows once its field is blurred.
    await waitFor(() =>
      expect(
        screen.getAllByText(labelMustBePositiveIntegerOrZero)
      ).toHaveLength(2)
    );
  });

  it('opens the notification settings read-only without write access', async () => {
    await openService({
      hasWriteAccess: false,
      isAdditiveInheritanceEnabled: true
    });

    await expectSingleChip(labelLinkedContacts, 'admin');

    expect(await getInput('interval')).toBeDisabled();
    expect(screen.getByTestId(testId('contacts'))).toBeDisabled();
    expect(screen.getByTestId(testId('timeperiod'))).toBeDisabled();
    expect(getSwitch('use-only-contacts-from-host')).toBeDisabled();
    expect(getSwitch('contact-additive-inheritance')).toBeDisabled();
    expect(getOption(labelWarning)).toHaveAttribute('aria-disabled', 'true');
  });
};
