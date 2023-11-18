import { Provider, createStore } from 'jotai';
import { pick } from 'ramda';

import { TestQueryProvider, Method } from '@centreon/ui';
import {
  userAtom,
  refreshIntervalAtom,
  downtimeAtom,
  acknowledgementAtom,
  aclAtom
} from '@centreon/ui-context';

import {
  labelDown,
  labelEndDateGreaterThanStartDate,
  labelMoreActions,
  labelSetDowntime,
  labelSubmitStatus,
  labelUnreachable,
  labelUp
} from '../translatedLabels';
import { resourcesEndpoint } from '../api/endpoint';

import { selectedResourcesAtom } from './actionsAtoms';

import Actions from '.';

const mockUser = {
  alias: 'admin',
  isExportButtonEnabled: true,
  locale: 'en',
  timezone: 'Europe/Paris'
};
const mockRefreshInterval = 15;
const mockDowntime = {
  duration: 7200,
  fixed: true,
  with_services: false
};
const mockAcl = {
  actions: {
    host: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      forced_check: true,
      submit_status: true
    },
    service: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      forced_check: true,
      submit_status: true
    }
  }
};
const mockAcknowledgement = {
  force_active_checks: false,
  notify: false,
  persistent: true,
  sticky: true,
  with_services: true
};

const host = {
  has_passive_checks_enabled: true,
  id: 0,
  parent: null,
  type: 'host'
};

const initialize = (): ReturnType<typeof createStore> => {
  cy.clock(new Date(2020, 1, 1));
  cy.viewport('macbook-13');

  cy.interceptAPIRequest({
    alias: 'submitStatus',
    method: Method.POST,
    path: `${resourcesEndpoint}/submit`
  });

  const store = createStore();
  store.set(userAtom, mockUser);
  store.set(refreshIntervalAtom, mockRefreshInterval);
  store.set(downtimeAtom, mockDowntime);
  store.set(aclAtom, mockAcl);
  store.set(acknowledgementAtom, mockAcknowledgement);

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestQueryProvider>
          <Actions onRefresh={cy.stub()} />
        </TestQueryProvider>
      </Provider>
    )
  });

  return store;
};

describe('Actions', () => {
  it('cannot send a downtime request when Downtime action is clicked and start date is greater than end date', () => {
    const store = initialize();
    store.set(selectedResourcesAtom, [host]);

    cy.findByLabelText(labelSetDowntime).click();

    cy.get('input').eq(0).type('03');

    cy.contains(labelEndDateGreaterThanStartDate).should('be.visible');

    cy.findByTestId('Confirm').should('be.disabled');

    cy.makeSnapshot();
  });

  it('sends a submit status request when a Resource is selected and the Submit status action is clicked', () => {
    const store = initialize();
    store.set(selectedResourcesAtom, [host]);

    cy.findByLabelText(labelMoreActions).click();
    cy.findByTestId(labelSubmitStatus).click();

    cy.contains(labelUp).click();
    cy.contains(labelDown).should('be.visible');
    cy.contains(labelUnreachable).should('be.visible');
    cy.contains(labelDown).click({ force: true });

    const output = 'output';
    const performanceData = 'performance data';

    cy.findByLabelText('Output').type(output, { force: true });
    cy.findByLabelText('Performance data').type(performanceData, {
      force: true
    });

    cy.findByTestId('Confirm').click({ force: true });

    cy.waitForRequest('@submitStatus').then(({ request }) => {
      expect(request.body).to.deep.equal({
        resources: [
          {
            ...pick(['type', 'id', 'parent'], host),
            output,
            performance_data: performanceData,
            status: 1
          }
        ]
      });
    });

    cy.makeSnapshot();
  });
});
