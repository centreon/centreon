import { TestQueryProvider, Method, SnackbarProvider } from '@centreon/ui';

import {
  labelDelete,
  labelSave,
  labelDuplicate,
  labelActiveOrInactive,
  labelChangeName,
  labelNotificationName,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelDoYouWantToConfirmAction,
  labelSuccessfulNotificationAdded,
  labelConfirmAddNotification,
  labelClosePanel,
  labelDoYouWantToQuitWithoutSaving,
  labelYourFormHasUnsavedChanges
} from '../translatedLabels';

import { notificationtEndpoint } from './api/endpoints';
import {
  usersResponse,
  hostGroupsResponse,
  serviceGroupsResponse
} from './testUtils';

import Form from '.';

const PanelWithQueryProvider = (): JSX.Element => {
  return (
    <div style={{ height: '100vh' }}>
      <TestQueryProvider>
        <SnackbarProvider>
          <Form />
        </SnackbarProvider>
      </TestQueryProvider>
    </div>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'addNotificationRequest',
    method: Method.POST,
    path: notificationtEndpoint({}),
    response: { status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'getHostsGroupsEndpoint',
    method: Method.GET,
    path: '**hosts/groups**',
    response: hostGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'getServiceGroupsEndpoint',
    method: Method.GET,
    path: '**services/groups**',
    response: serviceGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'getUsersEndpoint',
    method: Method.GET,
    path: '**users**',
    response: usersResponse
  });

  cy.mount({
    Component: <PanelWithQueryProvider />
  });

  cy.viewport(1200, 1000);
};

describe('Panel : Creation mode', () => {
  beforeEach(initialize);

  it('Ensures that the header displays only the save icon', () => {
    cy.findByLabelText(labelSave).should('be.visible');
    cy.findByLabelText(labelDelete).should('not.exist');
    cy.findByLabelText(labelDuplicate).should('not.exist');

    cy.matchImageSnapshot();
  });

  it('Confirms that the save button is correctly initialized in a disabled state', () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Tests that the active/inactive checkbox is initially enabled.', () => {
    cy.findByLabelText(labelActiveOrInactive).should('be.visible');
    cy.findByLabelText(labelActiveOrInactive).should('not.be.disabled');

    cy.matchImageSnapshot();
  });

  it('Confirms that the notification name is correctly initialized with the default value', () => {
    cy.findByTestId(labelChangeName).click();

    cy.findByPlaceholderText(labelNotificationName).should(
      'have.value',
      'Notification #1'
    );

    cy.matchImageSnapshot();
  });

  it('Confirms that the save button is correctly activated when all required fields are filled, and the form is error-free, allowing the user to save the form data', () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findByLabelText(labelSearchServiceGroups).click();
    cy.waitForRequest('@getServiceGroupsEndpoint');
    cy.findByText('MySQL-Servers').click();

    cy.findByLabelText('Search users').click();
    cy.waitForRequest('@getUsersEndpoint');
    cy.findByText('Guest').click();

    cy.findByTestId('EmailBody').type('Bonjour');

    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.matchImageSnapshot();
  });

  it('Confirms that the save button triggers the display of a confirmation dialog, providing the user with an additional confirmation step before proceeding with the action', () => {
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findByLabelText(labelSearchServiceGroups).click();
    cy.waitForRequest('@getServiceGroupsEndpoint');
    cy.findByText('MySQL-Servers').click();

    cy.findByLabelText('Search users').click();
    cy.waitForRequest('@getUsersEndpoint');
    cy.findByText('Guest').click();

    cy.findByTestId('EmailBody').type('Bonjour');

    cy.findByLabelText(labelSave).click();

    cy.findByText(labelDoYouWantToConfirmAction);
    cy.findByText(labelConfirmAddNotification);

    cy.matchImageSnapshot();
  });

  it('Tests that a POST request is sent when the confirm button is clicked', () => {
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findByLabelText(labelSearchServiceGroups).click();
    cy.waitForRequest('@getServiceGroupsEndpoint');
    cy.findByText('MySQL-Servers').click();

    cy.findByLabelText('Search users').click();
    cy.waitForRequest('@getUsersEndpoint');
    cy.findByText('Guest').click();

    cy.findByTestId('EmailBody').type('Bonjour');

    cy.findByLabelText(labelSave).click();

    cy.findByLabelText('Confirm').click();

    cy.waitForRequest('@addNotificationRequest');
    cy.findByText(labelSuccessfulNotificationAdded).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('Confirms that the close button triggers the display of a confirmation dialog if the user has made some changes to the form', () => {
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findByLabelText(labelClosePanel).click();

    cy.findByText(labelYourFormHasUnsavedChanges);
    cy.findByText(labelDoYouWantToQuitWithoutSaving);

    cy.matchImageSnapshot();
  });
});
