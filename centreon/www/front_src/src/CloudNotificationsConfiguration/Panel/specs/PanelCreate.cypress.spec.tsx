import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import Form from '..';
import { panelWidthStorageAtom } from '../../atom';
import {
  labelActiveOrInactive,
  labelChangeName,
  labelClosePanel,
  labelDelete,
  labelDoYouWantToQuitWithoutSaving,
  labelDuplicate,
  labelNotificationName,
  labelSave,
  labelSearchBusinessViews,
  labelSearchContacts,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelSelectTimePeriod,
  labelSubject,
  labelSuccessfulNotificationAdded,
  labelYourFormHasUnsavedChanges
} from '../../translatedLabels';
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
    alias: 'getAvailableTimePeriodEndpoint',
    method: Method.GET,
    path: `${availableTimePeriodsEndpoint}**`,
    response: timePeriodsResponse
  });

  cy.viewport('macbook-13');

  cy.mount({
    Component: <PanelWithQueryProvider />
  });
};

const fillFormRequiredFields = (): void => {
  cy.findByLabelText(labelNotificationName).type('notification#1');
  cy.findByLabelText(labelSearchHostGroups).click();
  cy.waitForRequest('@getHostsGroupsEndpoint');
  cy.findByText('Firewall').click();

  cy.findByLabelText(labelSearchServiceGroups).click();
  cy.waitForRequest('@getServiceGroupsEndpoint');
  cy.findByText('MySQL-Servers').click();

  cy.findByLabelText(labelSelectTimePeriod).click();
  cy.waitForRequest('@getAvailableTimePeriodEndpoint');
  cy.findByText('24X7').click();

  cy.findByLabelText(labelSearchContacts).click();
  cy.waitForRequest('@getUsersEndpoint');
  cy.findByText('Guest').click();
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

    fillFormRequiredFields();
    cy.clickOutside();

    cy.get('#panel-content').scrollTo('top');

    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('sends a request to add a new notification with the form values when the Confirm button is clicked', () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    fillFormRequiredFields();
    cy.clickOutside();

    cy.get('#panel-content').scrollTo('top');

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@addNotificationRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(formData);
    });

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

    emailBodyText.forEach((text) => {
      cy.findByTestId('EmailBody').contains(text);
    });

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
