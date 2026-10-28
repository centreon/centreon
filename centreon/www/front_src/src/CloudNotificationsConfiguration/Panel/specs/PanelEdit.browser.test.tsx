import { SnackbarProvider } from '@centreon/ui';
import {
  type PlatformVersions,
  platformVersionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  waitForRequest
} from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { DeleteConfirmationDialog } from '../../Actions/Delete';
import { DuplicationForm } from '../../Actions/Duplicate';
import { notificationsNamesAtom, panelWidthStorageAtom } from '../../atom';
import {
  labelActiveOrInactive,
  labelBusinessViewsEvents,
  labelCancel,
  labelChangeName,
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneContact,
  labelClosePanel,
  labelDelete,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelDiscard,
  labelDuplicate,
  labelExpandInformationPanel,
  labelMessageFieldShouldNotBeEmpty,
  labelNotificationDuplicated,
  labelNotificationName,
  labelNotificationSuccessfullyDeleted,
  labelPleaseEnterNameForDuplicatedNotification,
  labelReduceInformationPanel,
  labelRequired,
  labelSave,
  labelSearchBusinessViews,
  labelSearchContacts,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelSelectTimePeriod,
  labelSubject,
  labelSuccessfulEditNotification,
  labelThisNameAlreadyExists,
  labelTimePeriod,
  labelTimePeriodFieldShouldNotBeEmpty,
  labelTypeYourTextHere
} from '../../translatedLabels';
import Form from '..';
import {
  availableTimePeriodsEndpoint,
  notificationEndpoint
} from '../api/endpoints';
import { editedNotificationIdAtom, panelModeAtom } from '../atom';
import { PanelMode } from '../models';
import {
  getNotificationResponse,
  platformVersions,
  timePeriodsResponse
} from './testUtils';

interface InitializeProps {
  isBamModuleInstalled: boolean;
}

const initialize = ({ isBamModuleInstalled }: InitializeProps): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();
  store.set(panelWidthStorageAtom, 800);
  store.set(panelModeAtom, PanelMode.Edit);
  store.set(editedNotificationIdAtom, 1);
  store.set(notificationsNamesAtom, [
    { id: 1, name: 'Notifications 1' },
    { id: 2, name: 'Notifications 2' }
  ]);

  if (isBamModuleInstalled) {
    store.set(platformVersionsAtom, platformVersions as PlatformVersions);
  }

  interceptAPIRequest({
    alias: 'getNotificationRequest',
    method: Method.GET,
    path: notificationEndpoint({ id: 1 }),
    response: getNotificationResponse({ isBamModuleInstalled })
  });

  interceptAPIRequest({
    alias: 'editNotificationRequest',
    method: Method.PUT,
    path: notificationEndpoint({ id: 1 }),
    response: { status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'deleteNotificationtRequest',
    method: Method.DELETE,
    path: notificationEndpoint({ id: 1 }),
    response: {
      data: [
        {
          status: 204
        }
      ]
    }
  });

  interceptAPIRequest({
    alias: 'duplicateNotificationtRequest',
    method: Method.POST,
    path: notificationEndpoint({}),
    response: { status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'getAvailableTimePeriodEndpoint',
    method: Method.GET,
    path: `${availableTimePeriodsEndpoint}**`,
    response: timePeriodsResponse
  });

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  render(
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>
          <SnackbarProvider>
            <div>
              <Form marginBottom={0} />
              <DeleteConfirmationDialog />
              <DuplicationForm />
            </div>
          </SnackbarProvider>
        </QueryClientProvider>
      </Provider>
    </div>
  );
};

// The notification is loaded once its values fill the form.
const waitForNotification = async (): Promise<void> => {
  await waitForRequest('@getNotificationRequest');
  await screen.findByText('Firewall');
};

const clickOutside = async (): Promise<void> => {
  await userEvent.click(document.body);
};

const getCheckboxes = (testId: string): Array<HTMLElement> =>
  within(screen.getByTestId(testId)).getAllByRole('checkbox');

const getCheckbox = (testId: string): HTMLElement =>
  within(screen.getByTestId(testId)).getByRole('checkbox');

const expectCheckedStates = (testId: string, states: Array<boolean>): void => {
  const checkboxes = getCheckboxes(testId);

  expect(checkboxes).toHaveLength(states.length);
  states.forEach((checked, index) => {
    if (checked) {
      expect(checkboxes[index]).toBeChecked();

      return;
    }
    expect(checkboxes[index]).not.toBeChecked();
  });
};

// The chips of a multiple autocomplete, by their label.
const getChips = (testId: string): Array<string | null> =>
  Array.from(
    (
      screen.getByTestId(testId).closest('.MuiAutocomplete-root') as HTMLElement
    ).querySelectorAll('.MuiChip-label')
  ).map((chip) => chip.textContent);

// The clear button of an autocomplete only shows on hover: like a user, the
// pointer goes over the field first.
const clearField = async (index: number): Promise<void> => {
  const clear = screen.getAllByLabelText('Clear')[index];

  await userEvent.hover(clear.closest('.MuiAutocomplete-root') as HTMLElement);
  await userEvent.click(clear);
};

const getActivateSwitch = (): HTMLElement =>
  within(screen.getByLabelText(labelActiveOrInactive)).getByRole('checkbox');

const getEmailBody = (): HTMLElement => screen.getByTestId('EmailBody');

const isBefore = (first: HTMLElement, second: HTMLElement): boolean =>
  Boolean(
    first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING
  );

describe('Edit Panel', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize({ isBamModuleInstalled: false });
  });

  it('ensures that the header section displays all the expected actions', async () => {
    await waitForNotification();

    expect(screen.getByLabelText(labelDeleteNotification)).toBeVisible();
    expect(screen.getByLabelText(labelSave)).toBeVisible();
    expect(screen.getByLabelText(labelDuplicate)).toBeVisible();
    expect(screen.getByLabelText(labelActiveOrInactive)).toBeVisible();
    expect(screen.getByLabelText(labelClosePanel)).toBeVisible();

    // The name, as a title, and the actions in this order: activation,
    // duplication, save, deletion, closing.
    expect(screen.getByText('Notifications 1')).toBeVisible();
    const actions = [
      screen.getByLabelText(labelActiveOrInactive),
      screen.getByLabelText(labelDuplicate),
      screen.getByLabelText(labelSave),
      screen.getByLabelText(labelDeleteNotification),
      screen.getByLabelText(labelClosePanel)
    ];
    actions.slice(1).forEach((action, index) => {
      expect(isBefore(actions[index], action)).toBe(true);
    });
    // The notification is inactive, the save button disabled until a change.
    expect(getActivateSwitch()).not.toBeChecked();
    expect(screen.getByLabelText(labelSave)).toBeDisabled();
    expect(screen.getByLabelText(labelDuplicate)).toBeEnabled();
    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue(
      '24h/24 - 7/7 days'
    );
  });

  it('confirms that the notification name is properly rendered with the edited value and supports the capability for users to modify the name by interacting with the Edit icon', async () => {
    await waitForNotification();

    const notificationName = 'Notifications 1';
    expect(screen.getByText(notificationName)).toBeVisible();
    await userEvent.click(screen.getByTestId(labelChangeName));
    expect(
      await screen.findByLabelText(labelNotificationName)
    ).toBeInTheDocument();
    expect(screen.queryByText(labelChangeName)).not.toBeInTheDocument();

    expect(screen.getByLabelText(labelNotificationName)).toHaveValue(
      notificationName
    );
    expect(screen.getByLabelText(labelNotificationName)).toHaveAttribute(
      'required'
    );

    // The edit icon gives way to the field.
    expect(screen.queryByTestId(labelChangeName)).not.toBeInTheDocument();
  });

  it('ensures that the form handles an empty name field correctly by showing an error message and disabling the Save button as a validation measure', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelChangeName));

    await userEvent.clear(await screen.findByLabelText(labelNotificationName));
    await clickOutside();

    expect(await screen.findByText(labelRequired)).toBeVisible();
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(screen.getByLabelText(labelNotificationName)).toHaveValue('');
  });

  it('ensures that the form handles an existing name field correctly by showing an error message and disabling the Save button as a validation measure', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelChangeName));

    const name = await screen.findByLabelText(labelNotificationName);
    await userEvent.clear(name);
    await userEvent.type(name, 'Notifications 2');
    await clickOutside();

    expect(await screen.findByText(labelThisNameAlreadyExists)).toBeVisible();
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(name).toHaveValue('Notifications 2');
  });

  it('confirms that the "Expand/Collapse" button triggers the desired expansion or collapse of the panel, providing users with the ability to control its visibility and size', async () => {
    await waitForNotification();

    expect(screen.getByText(labelReduceInformationPanel)).toBeVisible();
    await userEvent.click(screen.getByTestId(labelReduceInformationPanel));
    expect(await screen.findByText(labelExpandInformationPanel)).toBeVisible();
    await waitFor(() => {
      expect(localStorage.getItem('cloud-notifications-panel-width')).toBe(
        '550'
      );
    });

    await userEvent.click(screen.getByTestId(labelReduceInformationPanel));
    expect(await screen.findByText(labelReduceInformationPanel)).toBeVisible();
    await waitFor(() => {
      expect(localStorage.getItem('cloud-notifications-panel-width')).toBe(
        '800'
      );
    });
  });

  it("ensures that the Save button's initial state is set to disabled", async () => {
    await waitForNotification();

    expect(screen.getByLabelText(labelSave)).toBeDisabled();
  });

  it('confirms that the Save button responds to field changes correctly, becoming enabled when a modification occurs and the form is error-free', async () => {
    await waitForNotification();

    expect(screen.getByLabelText(labelSave)).toBeDisabled();
    await userEvent.click(getActivateSwitch());
    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).not.toBeDisabled();
    });

    // The switch is on, and a changed notification cannot be duplicated.
    expect(getActivateSwitch()).toBeChecked();
    expect(screen.getByLabelText(labelDuplicate)).toBeDisabled();
  });

  it('displays host group resources and events with the edited notification values', async () => {
    await waitForNotification();

    expect(screen.getByLabelText(labelSearchHostGroups)).toBeVisible();
    expect(screen.getByText('Firewall')).toBeVisible();
    expect(screen.getByText('Linux-Servers')).toBeVisible();
    expect(screen.getByText('Networks')).toBeVisible();
    expect(screen.getByText('Printers')).toBeVisible();

    await waitFor(() => {
      expectCheckedStates('Host groups events', [true, true, false]);
    });

    // In this order, and the events can be changed.
    expect(getChips(labelSearchHostGroups)).toEqual([
      'Firewall',
      'Linux-Servers',
      'Networks',
      'Printers'
    ]);
    getCheckboxes('Host groups events').forEach((checkbox) => {
      expect(checkbox).toBeEnabled();
    });
  });

  it('ensures that the "Include Services" field presents the value of the edited notification', async () => {
    await waitForNotification();

    await waitFor(() => {
      expect(getCheckbox('include Services')).toBeChecked();
    });

    expectCheckedStates('Extra events services', [true, true, false, false]);

    getCheckboxes('Extra events services').forEach((checkbox) => {
      expect(checkbox).toBeEnabled();
    });
  });

  it('confirms that the "Include Services" checkbox controls the enabling and checking of all host group services checkboxes', async () => {
    await waitForNotification();

    await waitFor(() => {
      expect(getCheckbox('include Services')).toBeChecked();
    });
    await userEvent.click(screen.getByTestId('include Services'));

    await waitFor(() => {
      expect(getCheckbox('include Services')).not.toBeChecked();
    });
    const checkboxes = getCheckboxes('Extra events services');
    expect(checkboxes).toHaveLength(4);
    await waitFor(() => {
      checkboxes.forEach((checkbox) => {
        expect(checkbox).not.toBeChecked();
        expect(checkbox).toBeDisabled();
      });
    });
  });

  it('ensures that when the "Host Groups" field is empty, all event checkboxes are unchecked and the "Include Services" field is not visible.', async () => {
    await waitForNotification();

    expect(screen.getByTestId('include Services')).toBeVisible();
    expect(screen.getByTestId('Extra events services')).toBeVisible();

    // The first clear button each time: the host groups, then the service
    // groups and the time period, once the fields before are empty.
    await clearField(0);
    await clearField(0);
    await clearField(0);

    await waitFor(() => {
      expect(screen.queryByTestId('include Services')).not.toBeInTheDocument();
    });

    const checkboxes = getCheckboxes('Host groups events');
    expect(checkboxes).toHaveLength(3);
    checkboxes.forEach((checkbox) => {
      expect(checkbox).not.toBeChecked();
    });

    expect(
      screen.queryByTestId('Extra events services')
    ).not.toBeInTheDocument();

    // All three fields are empty, the events disabled, and both resource
    // fields ask for a resource.
    expect(getChips(labelSearchHostGroups)).toEqual([]);
    expect(getChips(labelSearchServiceGroups)).toEqual([]);
    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue('');
    checkboxes.forEach((checkbox) => {
      expect(checkbox).toBeDisabled();
    });
    expect(
      await screen.findAllByText(labelChooseAtLeastOneResource)
    ).toHaveLength(2);
  });

  it('displays service groups and events fields with the edited notification values', async () => {
    await waitForNotification();

    expect(screen.getByLabelText(labelSearchServiceGroups)).toBeVisible();
    expect(screen.getByText('service1')).toBeVisible();
    expect(screen.getByText('service2')).toBeVisible();
    expect(screen.getByText('service3')).toBeVisible();

    await waitFor(() => {
      expectCheckedStates('Service groups events', [false, true, true, true]);
    });

    expect(getChips(labelSearchServiceGroups)).toEqual([
      'service1',
      'service2',
      'service3'
    ]);
    getCheckboxes('Service groups events').forEach((checkbox) => {
      expect(checkbox).toBeEnabled();
    });
  });

  it('ensures that when the Service Groups field is empty, all associated events are disabled and unchecked', async () => {
    await waitForNotification();

    await clearField(1);

    const checkboxes = getCheckboxes('Service groups events');
    expect(checkboxes).toHaveLength(4);
    await waitFor(() => {
      checkboxes.forEach((checkbox) => {
        expect(checkbox).not.toBeChecked();
        expect(checkbox).toBeDisabled();
      });
    });

    // Only the service groups are emptied: the host groups still provide a
    // resource, and the save button is enabled.
    expect(getChips(labelSearchServiceGroups)).toEqual([]);
    expect(getChips(labelSearchHostGroups)).toHaveLength(4);
    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    expect(
      screen.queryByText(labelChooseAtLeastOneResource)
    ).not.toBeInTheDocument();
  });

  it('validates that when both resource fields are empty, the user interface responds by displaying an error message and disabling the Save button', async () => {
    await waitForNotification();

    await clearField(0);
    await clearField(0);
    await userEvent.click(screen.getByTestId(labelSearchHostGroups));

    await waitFor(() => {
      expect(screen.getAllByText(labelChooseAtLeastOneResource)).toHaveLength(
        2
      );
    });
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    // The time period is kept.
    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue(
      '24h/24 - 7/7 days'
    );
  });

  it('displays the Contacts field with edited notification contacts', async () => {
    await waitForNotification();
    const fieldContacts = screen.getByTestId('Search contacts');

    const field = fieldContacts.parentElement as HTMLElement;
    expect(within(field).getByText('centreon-gorgone')).toBeVisible();
    expect(within(field).getByText('Guest')).toBeVisible();

    expect(getChips('Search contacts')).toEqual(['centreon-gorgone', 'Guest']);
  });

  it('validates that when the Contacts field is empty, the user interface responds by displaying an error message and disabling the Save button', async () => {
    await waitForNotification();

    await clearField(3);
    await userEvent.click(screen.getByTestId(labelSearchContacts));

    await clickOutside();

    await waitFor(() => {
      expect(screen.getAllByText(labelChooseAtleastOneContact)).toHaveLength(1);
    });
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(getChips('Search contacts')).toEqual([]);
  });

  it('validates that when the Time period field is empty, the user interface responds by displaying an error message and disabling the Save button', async () => {
    await waitForNotification();

    await clearField(2);
    await userEvent.click(screen.getByTestId(labelTimePeriod));

    await clickOutside();

    await waitFor(() => {
      expect(
        screen.getAllByText(labelTimePeriodFieldShouldNotBeEmpty)
      ).toHaveLength(1);
    });
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue('');
  });

  it('confirms that the three icons for notification channels are appropriately presented, with the email icon initially selected and the other icons disabled', async () => {
    await waitForNotification();

    expect(getCheckbox('Email')).toBeChecked();

    expect(getCheckbox('SMS')).not.toBeChecked();
    expect(getCheckbox('SMS')).toBeDisabled();

    expect(getCheckbox('Slack')).not.toBeChecked();
    expect(getCheckbox('Slack')).toBeDisabled();

    // Email, SMS then Slack, each with its icon.
    const channels = ['Email', 'SMS', 'Slack'].map((channel) =>
      screen.getByTestId(channel)
    );
    expect(isBefore(channels[0], channels[1])).toBe(true);
    expect(isBefore(channels[1], channels[2])).toBe(true);
    channels.forEach((channel) => {
      expect(
        (channel.parentElement as HTMLElement).querySelector('svg')
      ).toBeVisible();
    });
  });

  it('confirms that the Subject field is properly rendered with the edited notification subject', async () => {
    await waitForNotification();

    expect(screen.getByLabelText(labelSubject)).toHaveValue('Notification');
  });

  it('validates that when the Subject field is empty, the user interface responds by displaying an error message and disabling the Save button', async () => {
    await waitForNotification();

    await userEvent.clear(screen.getByLabelText(labelSubject));
    await clickOutside();

    expect(await screen.findByText(labelRequired)).toBeVisible();
    expect(screen.getByLabelText(labelSave)).toBeDisabled();
  });

  it('confirms that the Message field is properly rendered with the edited notification message', async () => {
    await waitForNotification();

    await waitFor(() => {
      expect(getEmailBody()).toHaveTextContent('Hello');
    });

    // "there" in bold, then a heading whose "far" is underlined and struck
    // through.
    const emailBody = getEmailBody();
    expect(emailBody).toHaveTextContent(
      /^Hello there,How are you doing so far\?$/
    );
    expect(emailBody.querySelector('strong')).toHaveTextContent(/^there$/);
    const heading = emailBody.querySelector('h5') as HTMLElement;
    expect(heading).toHaveTextContent(/^How are you doing so far\?$/);
    const far = Array.from(heading.querySelectorAll('span')).find(
      (span) => span.textContent === 'far'
    ) as HTMLElement;
    expect(getComputedStyle(far).textDecorationLine).toMatch(
      /underline line-through/
    );
  });

  it('validates that when the Message field is empty, the user interface responds by displaying an error message and disabling the Save button', async () => {
    await waitForNotification();

    const emailBody = getEmailBody();
    await waitFor(() => {
      expect(emailBody).toHaveTextContent('Hello');
    });

    // Selects the whole message, then deletes it.
    emailBody.focus();
    await userEvent.keyboard('{Control>}a{/Control}');
    await userEvent.keyboard('{Backspace}');
    await waitFor(() => {
      expect(emailBody).toHaveTextContent(/^$/);
    });
    await clickOutside();

    expect(
      await screen.findByText(labelMessageFieldShouldNotBeEmpty)
    ).toBeVisible();
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(
      within(emailBody.parentElement as HTMLElement).getByText(
        labelTypeYourTextHere
      )
    ).toBeVisible();
  });
});

describe('Edit Panel : Confirm Dialog', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize({ isBamModuleInstalled: false });
  });

  it('confirms that the Confirm button triggers the sending of a PUT request', async () => {
    await waitForNotification();

    await userEvent.click(getActivateSwitch());
    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));

    const { request } = await waitForRequest('@editNotificationRequest');

    expect((request.body as { is_activated: boolean }).is_activated).toBe(true);

    expect(
      await screen.findByText(labelSuccessfulEditNotification)
    ).toBeVisible();
  });
});

describe('Edit Panel: Delete button', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize({ isBamModuleInstalled: false });
  });

  it('displays a confirmation dialog containing the notification name upon clicking the Delete button', async () => {
    await waitForNotification();

    const message = `${labelDelete} « Notifications 1 ».`;
    await userEvent.click(screen.getByTestId(labelDeleteNotification));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText(message)).toBeVisible();
    expect(within(dialog).getByText(labelDeleteNotification)).toBeVisible();
    expect(
      within(dialog).getByText(labelDeleteNotificationWarning)
    ).toBeVisible();
    await userEvent.click(within(dialog).getByText(labelCancel));

    // Cancelling closes the dialog and deletes nothing.
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(screen.getByText('Notifications 1')).toBeVisible();
  });

  it('displays a success message after successful deletion', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDeleteNotification));
    await userEvent.click(await screen.findByLabelText(labelDelete));

    await waitForRequest('@deleteNotificationtRequest');
    // The panel deleted the notification it had loaded.
    expect(getRequestCalls('@getNotificationRequest').length).toBeGreaterThan(
      0
    );

    expect(
      await screen.findByText(labelNotificationSuccessfullyDeleted)
    ).toBeVisible();

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('displays an error message upon failed deletion', async () => {
    interceptAPIRequest({
      alias: 'deleteNotificationtRequest',
      method: Method.DELETE,
      path: notificationEndpoint({ id: 1 }),
      response: {
        code: '500',
        message: 'internal server error'
      },
      statusCode: 500
    });

    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDeleteNotification));
    await userEvent.click(await screen.findByLabelText(labelDelete));
    await waitForRequest('@deleteNotificationtRequest');

    expect(await screen.findByText('internal server error')).toBeVisible();
  });
});

describe('Edit Panel: Duplicate button', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize({ isBamModuleInstalled: false });
  });

  it('disables the Duplicate button when changes are made', async () => {
    await waitForNotification();

    expect(screen.getByTestId(labelDuplicate)).not.toBeDisabled();

    await clearField(0);

    await waitFor(() => {
      expect(screen.getByTestId(labelDuplicate)).toBeDisabled();
    });
  });

  it('displays confirmation dialog with new notification name field on Duplicate button click', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDuplicate));

    expect(
      await screen.findByText(labelPleaseEnterNameForDuplicatedNotification)
    ).toBeVisible();
    expect(screen.getByLabelText(labelNotificationName)).toBeVisible();
    expect(
      within(screen.getByRole('dialog')).getByText(labelDuplicate)
    ).toBeDisabled();
    await userEvent.click(screen.getByText(labelDiscard));
  });

  it('validates that name field is not empty and not already taken', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDuplicate));
    const name = await screen.findByLabelText(labelNotificationName);
    expect(name).toHaveAttribute('required');
    await userEvent.type(name, 'Notifications 2');
    await clickOutside();
    expect(await screen.findByText(labelThisNameAlreadyExists)).toBeVisible();

    await userEvent.clear(name);
    await clickOutside();
    expect(await screen.findByText(labelRequired)).toBeVisible();

    await userEvent.click(screen.getByText(labelDiscard));
  });

  it('disables the Confirm button if the name is empty or already exists', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDuplicate));
    const name = await screen.findByLabelText(labelNotificationName);
    await userEvent.type(name, 'Notifications 2');
    await waitFor(() => {
      expect(screen.getByTestId('Confirm')).toBeDisabled();
    });

    await userEvent.clear(name);
    await waitFor(() => {
      expect(screen.getByTestId('Confirm')).toBeDisabled();
    });

    await userEvent.click(screen.getByText(labelDiscard));
  });

  it('displays a success message upon successful duplication', async () => {
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDuplicate));

    await userEvent.type(
      await screen.findByLabelText(labelNotificationName),
      'New name'
    );
    await waitFor(() => {
      expect(screen.getByTestId('Confirm')).toBeEnabled();
    });
    await userEvent.click(screen.getByTestId('Confirm'));

    await waitForRequest('@duplicateNotificationtRequest');

    expect(await screen.findByText(labelNotificationDuplicated)).toBeVisible();
  });

  it('displays an error message upon failed duplication request', async () => {
    const errorMessage = 'internal server error';

    interceptAPIRequest({
      alias: 'duplicateNotificationtRequest',
      method: Method.POST,
      path: notificationEndpoint({}),
      response: {
        code: '500',
        message: errorMessage
      },
      statusCode: 500
    });
    await waitForNotification();

    await userEvent.click(screen.getByTestId(labelDuplicate));

    await userEvent.type(
      await screen.findByLabelText(labelNotificationName),
      'New name'
    );
    await waitFor(() => {
      expect(screen.getByTestId('Confirm')).toBeEnabled();
    });
    await userEvent.click(screen.getByTestId('Confirm'));

    await waitForRequest('@duplicateNotificationtRequest');

    expect(await screen.findByText(errorMessage)).toBeVisible();
  });
});

describe('Edit Panel: Business Views', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize({ isBamModuleInstalled: true });
  });

  it('displays Business Views and their events with the edited notification values', async () => {
    expect(await screen.findByTestId(labelSearchBusinessViews)).toBeVisible();
    expect(await screen.findByText('bv1')).toBeVisible();
    expect(screen.getByText('bv2')).toBeVisible();

    await waitFor(() => {
      expectCheckedStates(labelBusinessViewsEvents, [false, true, true, true]);
    });

    // After the service groups, and its events can be changed.
    expect(
      isBefore(
        screen.getByTestId(labelSearchServiceGroups),
        screen.getByTestId(labelSearchBusinessViews)
      )
    ).toBe(true);
    expect(getChips(labelSearchBusinessViews)).toEqual(['bv1', 'bv2']);
    getCheckboxes(labelBusinessViewsEvents).forEach((checkbox) => {
      expect(checkbox).toBeEnabled();
    });
  });

  it('ensures that when the BA field is empty, all associated events are disabled and unchecked', async () => {
    await waitForNotification();

    await clearField(2);

    const checkboxes = getCheckboxes(labelBusinessViewsEvents);
    expect(checkboxes).toHaveLength(4);
    await waitFor(() => {
      checkboxes.forEach((checkbox) => {
        expect(checkbox).toBeDisabled();
        expect(checkbox).not.toBeChecked();
      });
    });

    // The other resources still count: the change can be saved.
    expect(getChips(labelSearchBusinessViews)).toEqual([]);
    expect(getChips(labelSearchServiceGroups)).toHaveLength(3);
    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
  });

  it('validates that when all resource fields are empty, the user interface responds by displaying an error message and disabling the Save button', async () => {
    await waitForNotification();

    await clearField(0);
    await clearField(0);
    await clearField(0);
    await userEvent.click(screen.getByTestId(labelSearchServiceGroups));

    await waitFor(() => {
      expect(screen.getAllByText(labelChooseAtLeastOneResource)).toHaveLength(
        3
      );
    });
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    // Only the resources are emptied: the time period is kept.
    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue(
      '24h/24 - 7/7 days'
    );
  });
});
