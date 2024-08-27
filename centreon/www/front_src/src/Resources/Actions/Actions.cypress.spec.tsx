import { Provider, createStore } from 'jotai';
import { pick } from 'ramda';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';

import { resourcesEndpoint } from '../api/endpoint';
import {
  labelAcknowledge,
  labelAcknowledgeCommandSent,
  labelAcknowledgeServices,
  labelActionNotPermitted,
  labelCheck,
  labelCheckDescription,
  labelComment,
  labelDisacknowledge,
  labelDisacknowledgeServices,
  labelDisacknowledgementCommandSent,
  labelDown,
  labelDowntimeCommandSent,
  labelDuration,
  labelEndDateGreaterThanStartDate,
  labelFixed,
  labelForcedCheck,
  labelForcedCheckCommandSent,
  labelMoreActions,
  labelNotify,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelSticky,
  labelSubmitStatus,
  labelUnreachable,
  labelUp
} from '../translatedLabels';

import { disacknowledgeEndpoint } from './Resource/Disacknowledge/api';
import { selectedResourcesAtom } from './actionsAtoms';
import {
  acknowledgeEndpoint,
  checkEndpoint,
  downtimeEndpoint
} from './api/endpoint';

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
const mockAcl = (canPerformActions = true): object => ({
  actions: {
    host: {
      acknowledgement: canPerformActions,
      check: canPerformActions,
      comment: canPerformActions,
      disacknowledgement: canPerformActions,
      downtime: canPerformActions,
      forced_check: canPerformActions,
      submit_status: canPerformActions
    },
    service: {
      acknowledgement: canPerformActions,
      check: canPerformActions,
      comment: canPerformActions,
      disacknowledgement: canPerformActions,
      downtime: canPerformActions,
      forced_check: canPerformActions,
      submit_status: canPerformActions
    }
  }
});

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

const service = {
  has_passive_checks_enabled: true,
  id: 0,
  parent: {
    id: 1,
    name: 'Host'
  },
  type: 'service'
};

const initialize = (): ReturnType<typeof createStore> => {
  cy.clock(new Date(2020, 1, 1));
  cy.viewport('macbook-13');

  cy.interceptAPIRequest({
    alias: 'submitStatus',
    method: Method.POST,
    path: `${resourcesEndpoint}/submit`
  });

  cy.interceptAPIRequest({
    alias: 'disacknowledgeResources',
    method: Method.DELETE,
    path: disacknowledgeEndpoint
  });

  cy.interceptAPIRequest({
    alias: 'acknowledgeResources',
    method: Method.POST,
    path: acknowledgeEndpoint
  });

  cy.interceptAPIRequest({
    alias: 'sendCheck',
    method: Method.POST,
    path: checkEndpoint
  });

  cy.interceptAPIRequest({
    alias: 'sendDowntime',
    method: Method.POST,
    path: downtimeEndpoint
  });

  const store = createStore();
  store.set(userAtom, mockUser);
  store.set(refreshIntervalAtom, mockRefreshInterval);
  store.set(downtimeAtom, mockDowntime);
  store.set(aclAtom, mockAcl());
  store.set(acknowledgementAtom, mockAcknowledgement);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <Provider store={store}>
          <TestQueryProvider>
            <Actions onRefresh={cy.stub()} />
          </TestQueryProvider>
        </Provider>
      </SnackbarProvider>
    )
  });

  return store;
};

describe('Actions', () => {
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

  describe('Disacknowledgement', () => {
    it('sends a disacknowledgement request with services disacknowledgement when a host is selected and the Disacknowledge action is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText(labelMoreActions).click();
      cy.contains(labelDisacknowledge).click();

      cy.findByLabelText(labelDisacknowledgeServices).should('be.checked');

      cy.findAllByLabelText(labelDisacknowledge).eq(1).click();

      cy.waitForRequest('@disacknowledgeResources').then(({ request }) => {
        expect(request.body.disacknowledgement.with_services).to.equal(true);
      });

      cy.contains(labelDisacknowledgementCommandSent).should('be.visible');

      cy.makeSnapshot();
    });

    it('sends a disacknowledgement request without services disacknowledgement when a host is selected and the Disacknowledge action is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText(labelMoreActions).click();
      cy.contains(labelDisacknowledge).click();

      cy.findByLabelText(labelDisacknowledgeServices).click();
      cy.findByLabelText(labelDisacknowledgeServices).should('not.be.checked');

      cy.findAllByLabelText(labelDisacknowledge).eq(1).click();

      cy.waitForRequest('@disacknowledgeResources').then(({ request }) => {
        expect(request.body.disacknowledgement.with_services).to.equal(false);
      });

      cy.contains(labelDisacknowledgementCommandSent).should('be.visible');

      cy.makeSnapshot();
    });

    it('sends a disacknowledgement request when a service is selected and the Disacknowledge action is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [service]);

      cy.findByLabelText(labelMoreActions).click();
      cy.contains(labelDisacknowledge).click();

      cy.findAllByLabelText(labelDisacknowledge).eq(1).click();

      cy.waitForRequest('@disacknowledgeResources').then(({ request }) => {
        expect(request.body.disacknowledgement.with_services).to.equal(true);
        expect(request.body.resources).to.deep.equal([
          {
            id: 0,
            parent: {
              id: 1
            },
            type: 'service'
          }
        ]);
      });

      cy.contains(labelDisacknowledgementCommandSent).should('be.visible');

      cy.makeSnapshot();
    });

    it('does not open the modal when the user has no ACL, a service is selected and the Disacknowledge action is clicked', () => {
      const store = initialize();
      store.set(aclAtom, mockAcl(false));
      store.set(selectedResourcesAtom, [service]);

      cy.findByLabelText(labelMoreActions).click();
      cy.contains(labelDisacknowledge).should(
        'have.attr',
        'aria-disabled',
        'true'
      );
      cy.contains(labelDisacknowledge)
        .parent()
        .should('have.attr', 'aria-label', labelActionNotPermitted);

      cy.makeSnapshot();
    });
  });

  describe('Acknowledgement', () => {
    it('sends an acknowledgement request when a host is selected and the Acknowledge action is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.contains(labelAcknowledge).click();

      cy.findByLabelText(labelComment).should(
        'have.value',
        'Acknowledged by admin'
      );
      cy.findByLabelText(labelNotify).should('not.be.checked');
      cy.findByLabelText(labelSticky).should('be.checked');
      cy.findByLabelText(labelAcknowledgeServices).should('be.checked');

      cy.findAllByLabelText(labelAcknowledge).eq(2).click();

      cy.waitForRequest('@acknowledgeResources').then(({ request }) => {
        expect(request.body.acknowledgement.comment).to.equal(
          'Acknowledged by admin'
        );
        expect(request.body.acknowledgement.with_services).to.equal(true);
        expect(request.body.acknowledgement.is_notify_contacts).to.equal(false);
        expect(request.body.acknowledgement.is_persistent_comment).to.equal(
          true
        );
        expect(request.body.acknowledgement.is_sticky).to.equal(true);
      });

      cy.contains(labelAcknowledgeCommandSent).should('be.visible');

      cy.makeSnapshot();
    });

    it('sends an acknowledgement request with updated parameters when a host is selected and the Acknowledge action is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.contains(labelAcknowledge).click();

      cy.findByLabelText(labelComment).clear().type('Acknowledged');
      cy.findByLabelText(labelNotify).check();
      cy.findByLabelText(labelSticky).uncheck();
      cy.findByLabelText(labelAcknowledgeServices).uncheck();

      cy.findAllByLabelText(labelAcknowledge).eq(2).click();

      cy.waitForRequest('@acknowledgeResources').then(({ request }) => {
        expect(request.body.acknowledgement.comment).to.equal('Acknowledged');
        expect(request.body.acknowledgement.with_services).to.equal(false);
        expect(request.body.acknowledgement.is_notify_contacts).to.equal(true);
        expect(request.body.acknowledgement.is_persistent_comment).to.equal(
          true
        );
        expect(request.body.acknowledgement.is_sticky).to.equal(false);
      });

      cy.contains(labelAcknowledgeCommandSent).should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Check', () => {
    it('sends a forced check request when a resource is selected and the forced check actions is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText(labelForcedCheck).click();

      cy.waitForRequest('@sendCheck').then(({ request }) => {
        expect(request.body).to.equal(
          '{"check":{"is_forced":true},"resources":[{"id":0,"parent":null,"type":"host"}]}'
        );
      });
      cy.contains(labelForcedCheckCommandSent).should('be.visible');

      cy.makeSnapshot();
    });

    it('sends a check request when a resource is selected and the forced check actions is clicked', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText('arrow').click();
      cy.contains(labelCheckDescription).click();

      cy.findByLabelText(labelCheck).click();

      cy.waitForRequest('@sendCheck').then(({ request }) => {
        expect(request.body).to.equal(
          '{"check":{"is_forced":false},"resources":[{"id":0,"parent":null,"type":"host"}]}'
        );
      });

      cy.makeSnapshot();
    });
  });

  describe('Downtime', () => {
    it('cannot send a downtime request when Downtime action is clicked and start date is greater than end date', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText(labelSetDowntime).click();

      cy.get('input').eq(0).type('03');

      cy.contains(labelEndDateGreaterThanStartDate).should('be.visible');

      cy.findByTestId('Confirm').should('be.disabled');

      cy.makeSnapshot();
    });

    it('sends a downtime request when Downtime action is clicked and the downtime form in saved', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText(labelSetDowntime).click();

      cy.findByTestId('Confirm').click();

      cy.waitForRequest('@sendDowntime').then(({ request }) => {
        expect(request.body.downtime.comment).to.equal('Downtime set by admin');
        expect(request.body.downtime.duration).to.equal(7200);
        expect(request.body.downtime.is_fixed).to.equal(true);
        expect(request.body.downtime.with_services).to.equal(false);
      });

      cy.contains(labelDowntimeCommandSent).should('be.visible');

      cy.makeSnapshot();
    });

    it('sends a downtime request when Downtime action is clicked and the downtime form is updated', () => {
      const store = initialize();
      store.set(selectedResourcesAtom, [host]);

      cy.findByLabelText(labelSetDowntime).click();

      cy.findByLabelText(labelFixed).click();
      cy.findAllByTestId(labelDuration).eq(0).clear().type('10000');
      cy.findByLabelText(labelSetDowntimeOnServices).check();

      cy.findByTestId('Confirm').click();

      cy.waitForRequest('@sendDowntime').then(({ request }) => {
        expect(request.body.downtime.comment).to.equal('Downtime set by admin');
        expect(request.body.downtime.duration).to.equal(10000);
        expect(request.body.downtime.is_fixed).to.equal(false);
        expect(request.body.downtime.with_services).to.equal(true);
      });

      cy.makeSnapshot();
    });
  });
});
