import { Provider, createStore } from 'jotai';

import { TestQueryProvider, Method, SnackbarProvider } from '@centreon/ui';

import {
  labelDelete,
  labelSave,
  labelDuplicate,
  labelActiveOrInactive,
  labelChangeName,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelDoYouWantToConfirmAction,
  labelSuccessfulNotificationAdded,
  labelConfirmAddNotification,
  labelClosePanel,
  labelDoYouWantToQuitWithoutSaving,
  labelYourFormHasUnsavedChanges,
  labelNotificationName,
  labelSubject,
  labelSearchBusinessViews
} from '../../translatedLabels';
import { panelWidthStorageAtom } from '../../atom';
import { contactGroupsEndpoint } from '../../../Authentication/api/endpoints';
import { platformVersionsAtom } from '../../../Main/atoms/platformVersionsAtom';
import {
  hostsGroupsEndpoint,
  notificationEndpoint,
  serviceGroupsEndpoint,
  usersEndpoint
} from '../api/endpoints';
import Form from '..';
import { defaultEmailSubject } from '../utils';

import {
  usersResponse,
  hostGroupsResponse,
  serviceGroupsResponse,
  contactGroupsResponse,
  platformVersions
} from './testUtils';

const store = createStore();
store.set(panelWidthStorageAtom, 800);

const PanelWithQueryProvider = (): JSX.Element => {
  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <Form marginBottom={0} />
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'addNotificationRequest',
    method: Method.POST,
    path: notificationEndpoint({}),
    response: { status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'getHostsGroupsEndpoint',
    method: Method.GET,
    path: `${hostsGroupsEndpoint}**`,
    response: hostGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'getServiceGroupsEndpoint',
    method: Method.GET,
    path: `${serviceGroupsEndpoint}**`,
    response: serviceGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'getUsersEndpoint',
    method: Method.GET,
    path: `${usersEndpoint}**`,
    response: usersResponse
  });

  cy.interceptAPIRequest({
    alias: 'contactGroupsEndpoint',
    method: Method.GET,
    path: `${contactGroupsEndpoint}**`,
    response: contactGroupsResponse
  });

  cy.viewport('macbook-13');

  cy.mount({
    Component: <PanelWithQueryProvider />
  });
};

describe('Create Panel', () => {
  beforeEach(initialize);

  it('displays the form', () => {
    cy.findByLabelText(labelSave).should('be.visible');
    cy.findByLabelText(labelDelete).should('not.exist');
    cy.findByLabelText(labelDuplicate).should('not.exist');

    cy.findByLabelText(labelSave).should('be.disabled');

    cy.findByLabelText(labelActiveOrInactive).should('be.visible');
    cy.findByLabelText(labelActiveOrInactive).should('not.be.disabled');

    cy.findByTestId(labelChangeName).should('not.exist');

    cy.findByLabelText(labelNotificationName).should('have.value', '');
    cy.findByLabelText(labelNotificationName).should('have.attr', 'required');

    cy.get('#panel-content').scrollTo('top');

    cy.makeSnapshot();
  });

  it('confirms that the Save button is correctly activated when all required fields are filled, and the form is error-free, allowing the user to save the form data', () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.findByLabelText(labelNotificationName).type('notification#1');
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findByLabelText(labelSearchServiceGroups).click();
    cy.waitForRequest('@getServiceGroupsEndpoint');
    cy.findByText('MySQL-Servers').click();

    cy.findByLabelText('Search contacts').click();
    cy.waitForRequest('@getUsersEndpoint');
    cy.findByText('Guest').click();

    cy.findByTestId('EmailBody').type('Bonjour');
    cy.findByLabelText(labelSubject).type('subject');

    cy.get('#panel-content').scrollTo('top');

    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Save button triggers the display of a confirmation dialog, providing the user with an additional confirmation step before proceeding with the action', () => {
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findAllByLabelText(labelNotificationName).type('Notification1');
    cy.findByLabelText(labelSearchServiceGroups).click();
    cy.waitForRequest('@getServiceGroupsEndpoint');
    cy.findByText('MySQL-Servers').click();

    cy.findByLabelText('Search contacts').click();
    cy.waitForRequest('@getUsersEndpoint');
    cy.findByText('Guest').click();

    cy.findByTestId('EmailBody').type('Bonjour');
    cy.findByLabelText(labelSubject).type('subject');

    cy.get('#panel-content').scrollTo('top');

    cy.findByLabelText(labelSave).click();

    cy.findByText(labelDoYouWantToConfirmAction);
    cy.findByText(labelConfirmAddNotification);

    cy.makeSnapshot();
  });

  it('tests that the form is sent when the confirm button is clicked', () => {
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.findAllByLabelText(labelNotificationName).type('Notification1');
    cy.findByLabelText(labelSearchServiceGroups).click();
    cy.waitForRequest('@getServiceGroupsEndpoint');
    cy.findByText('MySQL-Servers').click();

    cy.findByLabelText('Search contacts').click();
    cy.waitForRequest('@getUsersEndpoint');
    cy.findByText('Guest').click();

    cy.findByTestId('EmailBody').type('Bonjour');
    cy.findByLabelText(labelSubject).type('subject');

    cy.get('#panel-content').scrollTo('top');

    cy.findByLabelText(labelSave).click();

    cy.findByLabelText('Confirm').click();

    cy.waitForRequest('@addNotificationRequest');
    cy.findByText(labelSuccessfulNotificationAdded).should('be.visible');

    cy.makeSnapshot();
  });

  it('confirms that the Close button triggers the display of a confirmation dialog if the user has made some changes to the form', () => {
    cy.findByLabelText(labelSearchHostGroups).click();
    cy.waitForRequest('@getHostsGroupsEndpoint');
    cy.findByText('Firewall').click();

    cy.get('#panel-content').scrollTo('top');

    cy.findByLabelText(labelClosePanel).click();

    cy.findByText(labelYourFormHasUnsavedChanges);
    cy.findByText(labelDoYouWantToQuitWithoutSaving);

    cy.makeSnapshot();
  });

  it('displays the Email Subject field with the default initial value', () => {
    cy.get('#panel-content').scrollTo('bottom');

    cy.findByLabelText(labelSubject).should('have.value', defaultEmailSubject);

    cy.makeSnapshot();
  });

  it('displays the Email Body field with the default initial value', () => {
    cy.get('#panel-content').scrollTo('bottom');

    cy.findByTestId('EmailBody').contains('Centreon notification');
    cy.findByTestId('EmailBody').contains(
      'Notification Type: {{NOTIFICATIONTYPE}}'
    );

    cy.findByTestId('EmailBody').contains('Resource: {{NAME}}');
    cy.findByTestId('EmailBody').contains('ID: {{ID}}');
    cy.findByTestId('EmailBody').contains('Date/Time: {{SHORTDATETIME}}');
    cy.findByTestId('EmailBody').contains('Additional Info: {{OUTPUT}}');

    cy.makeSnapshot();
  });
});

describe('Create Panel: Business Views', () => {
  before(() => {
    store.set(platformVersionsAtom, platformVersions);
  });
  beforeEach(initialize);

  it('dispalys the businessViews field when the BAM module is installed', () => {
    cy.findByTestId(labelSearchBusinessViews).should('be.visible');

    cy.makeSnapshot();
  });
});
