import { SnackbarProvider } from '@centreon/ui';
import {
  type PlatformVersions,
  platformVersionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { panelWidthStorageAtom } from '../../atom';
import {
  labelActiveOrInactive,
  labelBusinessViewsEvents,
  labelChangeName,
  labelClosePanel,
  labelContacts,
  labelDelete,
  labelDoYouWantToQuitWithoutSaving,
  labelDuplicate,
  labelEmailTemplateForTheNotificationMessage,
  labelIncludeServicesForTheseHosts,
  labelNotificationName,
  labelNotificationSettings,
  labelSave,
  labelSearchBusinessViews,
  labelSearchContacts,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelSelectResourcesAndEvents,
  labelSelectTimePeriod,
  labelSubject,
  labelSuccessfulNotificationAdded,
  labelYourFormHasUnsavedChanges
} from '../../translatedLabels';
import Form from '..';
import {
  availableTimePeriodsEndpoint,
  hostsGroupsEndpoint,
  notificationEndpoint,
  serviceGroupsEndpoint,
  usersEndpoint
} from '../api/endpoints';
import { defaultEmailSubject } from '../utils';
import {
  emailBodyText,
  formData,
  hostGroupsResponse,
  platformVersions,
  serviceGroupsResponse,
  timePeriodsResponse,
  usersResponse
} from './testUtils';

interface InitializeProps {
  isBamModuleInstalled?: boolean;
}

const initialize = ({ isBamModuleInstalled = false }: InitializeProps = {}) => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();
  store.set(panelWidthStorageAtom, 800);

  if (isBamModuleInstalled) {
    store.set(platformVersionsAtom, platformVersions as PlatformVersions);
  }

  interceptAPIRequest({
    alias: 'addNotificationRequest',
    method: Method.POST,
    path: notificationEndpoint({}),
    response: { status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'getHostsGroupsEndpoint',
    method: Method.GET,
    path: `${hostsGroupsEndpoint}**`,
    response: hostGroupsResponse
  });

  interceptAPIRequest({
    alias: 'getServiceGroupsEndpoint',
    method: Method.GET,
    path: `${serviceGroupsEndpoint}**`,
    response: serviceGroupsResponse
  });

  interceptAPIRequest({
    alias: 'getUsersEndpoint',
    method: Method.GET,
    path: `${usersEndpoint}**`,
    response: usersResponse
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
            <Form marginBottom={0} />
          </SnackbarProvider>
        </QueryClientProvider>
      </Provider>
    </div>
  );
};

const clickOutside = async (): Promise<void> => {
  await userEvent.click(document.body);
};

const getCheckboxes = (testId: string): Array<HTMLElement> =>
  within(screen.getByTestId(testId)).getAllByRole('checkbox');

const selectOption = async (
  fieldLabel: string,
  alias: string,
  option: string
): Promise<void> => {
  await userEvent.click(screen.getByLabelText(fieldLabel));
  await waitForRequest(alias);
  await userEvent.click(await screen.findByText(option));
};

const fillFormRequiredFields = async (): Promise<void> => {
  await userEvent.type(
    await screen.findByLabelText(labelNotificationName),
    'notification#1'
  );

  await selectOption(
    labelSearchHostGroups,
    '@getHostsGroupsEndpoint',
    'Firewall'
  );
  await selectOption(
    labelSearchServiceGroups,
    '@getServiceGroupsEndpoint',
    'MySQL-Servers'
  );
  await selectOption(
    labelSelectTimePeriod,
    '@getAvailableTimePeriodEndpoint',
    '24X7'
  );
  await selectOption(labelSearchContacts, '@getUsersEndpoint', 'Guest');
};

// The chips of a multiple autocomplete, by their label.
const getChips = (testId: string): Array<string | null> =>
  Array.from(
    (
      screen.getByTestId(testId).closest('.MuiAutocomplete-root') as HTMLElement
    ).querySelectorAll('.MuiChip-label')
  ).map((chip) => chip.textContent);

describe('Create Panel', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize();
  });

  it('displays the form', async () => {
    expect(await screen.findByLabelText(labelSave)).toBeVisible();
    expect(screen.queryByLabelText(labelDelete)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(labelDuplicate)).not.toBeInTheDocument();

    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(screen.getByLabelText(labelActiveOrInactive)).toBeVisible();
    expect(screen.getByLabelText(labelActiveOrInactive)).not.toBeDisabled();

    expect(screen.queryByTestId(labelChangeName)).not.toBeInTheDocument();

    expect(screen.getByLabelText(labelNotificationName)).toHaveValue('');
    expect(screen.getByLabelText(labelNotificationName)).toHaveAttribute(
      'required'
    );

    // A new notification is active by default.
    expect(
      within(screen.getByLabelText(labelActiveOrInactive)).getByRole('checkbox')
    ).toBeChecked();

    // Both sections, with no resource selected: every event is disabled and
    // unchecked, and the services of the host groups are not offered.
    expect(screen.getByText(labelSelectResourcesAndEvents)).toBeVisible();
    expect(screen.getByText(labelNotificationSettings)).toBeVisible();
    ['Host groups events', 'Service groups events'].forEach((testId) => {
      getCheckboxes(testId).forEach((checkbox) => {
        expect(checkbox).not.toBeChecked();
        expect(checkbox).toBeDisabled();
      });
    });
    expect(getCheckboxes('Host groups events')).toHaveLength(3);
    expect(getCheckboxes('Service groups events')).toHaveLength(4);
    expect(screen.queryByTestId('include Services')).not.toBeInTheDocument();
    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue('');
  });

  it('confirms that the Save button is correctly activated when all required fields are filled, and the form is error-free, allowing the user to save the form data', async () => {
    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await fillFormRequiredFields();
    await clickOutside();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).not.toBeDisabled();
    });

    // The selected values, and the events of the selected resources enabled;
    // the services of the host groups are offered, not included yet.
    expect(screen.getByLabelText(labelNotificationName)).toHaveValue(
      'notification#1'
    );
    expect(getChips(labelSearchHostGroups)).toEqual(['Firewall']);
    expect(getChips(labelSearchServiceGroups)).toEqual(['MySQL-Servers']);
    expect(getChips('Search contacts')).toEqual(['Guest']);
    expect(screen.getByLabelText(labelSelectTimePeriod)).toHaveValue('24X7');
    ['Host groups events', 'Service groups events'].forEach((testId) => {
      getCheckboxes(testId).forEach((checkbox) => {
        expect(checkbox).not.toBeChecked();
        expect(checkbox).toBeEnabled();
      });
    });
    const includeServices = within(
      screen.getByTestId('include Services')
    ).getByRole('checkbox');
    expect(includeServices).not.toBeChecked();
    expect(includeServices).toBeEnabled();
    expect(screen.getByText(labelIncludeServicesForTheseHosts)).toBeVisible();
    getCheckboxes('Extra events services').forEach((checkbox) => {
      expect(checkbox).toBeDisabled();
    });
  });

  it('sends a request to add a new notification with the form values when the Confirm button is clicked', async () => {
    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await fillFormRequiredFields();
    await clickOutside();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).not.toBeDisabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));

    const { request } = await waitForRequest('@addNotificationRequest');

    expect(request.body).toEqual(formData);

    expect(
      await screen.findByText(labelSuccessfulNotificationAdded)
    ).toBeVisible();
  });

  it('confirms that the Close button triggers the display of a confirmation dialog if the user has made some changes to the form', async () => {
    await selectOption(
      labelSearchHostGroups,
      '@getHostsGroupsEndpoint',
      'Firewall'
    );

    await userEvent.click(screen.getByLabelText(labelClosePanel));

    const dialog = await screen.findByRole('dialog');

    expect(
      within(dialog).getByText(labelYourFormHasUnsavedChanges)
    ).toBeVisible();
    expect(
      within(dialog).getByText(labelDoYouWantToQuitWithoutSaving)
    ).toBeVisible();
    expect(within(dialog).getByText('Cancel')).toBeVisible();
    expect(within(dialog).getByText('Confirm')).toBeVisible();
  });

  it('displays the Email Subject field with the default initial value', async () => {
    expect(await screen.findByLabelText(labelSubject)).toHaveValue(
      defaultEmailSubject
    );

    expect(
      screen.getByText(labelEmailTemplateForTheNotificationMessage)
    ).toBeVisible();
  });

  it('displays the Email Body field with the default initial value', async () => {
    const emailBody = await screen.findByTestId('EmailBody');

    await waitFor(() => {
      emailBodyText.forEach((text) => {
        expect(emailBody).toHaveTextContent(text);
      });
    });

    // The email channel is the one selected, the other channels cannot be,
    // and no contact is selected yet.
    expect(
      within(screen.getByTestId('Email')).getByRole('checkbox')
    ).toBeChecked();
    ['SMS', 'Slack'].forEach((channel) => {
      const checkbox = within(screen.getByTestId(channel)).getByRole(
        'checkbox'
      );

      expect(checkbox).not.toBeChecked();
      expect(checkbox).toBeDisabled();
    });
    expect(screen.getByText(labelContacts)).toBeVisible();
    expect(getChips('Search contacts')).toEqual([]);
    // The title and the macros are in bold.
    expect(
      Array.from(emailBody.querySelectorAll('strong')).map(
        (strong) => strong.textContent
      )
    ).toEqual(['Centreon notification', '{{NOTIFICATIONTYPE}}', '{{STATE}}']);
  });
});

describe('Create Panel: Business Views', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize({ isBamModuleInstalled: true });
  });

  it('dispalys the businessViews field when the BAM module is installed', async () => {
    expect(await screen.findByTestId(labelSearchBusinessViews)).toBeVisible();

    // Below the service groups, with its events disabled until a business
    // view is selected.
    expect(
      screen
        .getByTestId(labelSearchServiceGroups)
        .compareDocumentPosition(screen.getByTestId(labelSearchBusinessViews)) &
        Node.DOCUMENT_POSITION_FOLLOWING
    ).toBeTruthy();
    getCheckboxes(labelBusinessViewsEvents).forEach((checkbox) => {
      expect(checkbox).not.toBeChecked();
      expect(checkbox).toBeDisabled();
    });
  });
});
