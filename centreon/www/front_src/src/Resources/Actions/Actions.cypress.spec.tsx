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
  labelAllColumns,
  labelAllPages,
  labelCheck,
  labelCheckDescription,
  labelComment,
  labelCurrentPageOnly,
  labelDisacknowledge,
  labelDisacknowledgeServices,
  labelDisacknowledgementCommandSent,
  labelDown,
  labelDowntimeCommandSent,
  labelDuration,
  labelEndDateGreaterThanStartDate,
  labelExport,
  labelExportProcessingInProgress,
  labelExportToCSV,
  labelFilterRessources,
  labelFilteredResources,
  labelFixed,
  labelForcedCheck,
  labelForcedCheckCommandSent,
  labelMoreActions,
  labelNotify,
  labelSelecetPages,
  labelSelectColumns,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelStickyForAnyNonOkStatus,
  labelSubmitStatus,
  labelUnreachable,
  labelUp,
  labelVisibleColumnsOnly,
  labelWarningExportToCsv
} from '../translatedLabels';

import { disacknowledgeEndpoint } from './Resource/Disacknowledge/api';
import { selectedResourcesAtom } from './actionsAtoms';
import {
  acknowledgeEndpoint,
  checkEndpoint,
  csvExportEndpoint,
  downtimeEndpoint
} from './api/endpoint';

import Actions from '.';
import { labelCancel } from '../../Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import { selectedColumnIdsAtom } from '../Listing/listingAtoms';

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

const anomalyDetection = {
  has_passive_checks_enabled: true,
  id: 0,
  parent: {
    id: 1,
    name: 'Host'
  },
  type: 'anomaly-detection'
};

const allColumns = [
  'status',
  'resource',
  'parent_resource',
  'duration',
  'tries',
  'last_check',
  'information',
  'severity',
  'notes_url',
  'action_url',
  'state',
  'alias',
  'parent_alias',
  'fqdn',
  'monitoring_server_name',
  'notification',
  'checks'
];
const visibleColumns = [
  'resource',
  'parent_resource',
  'duration',
  'last_check',
  'information',
  'tries'
];
const search = { $and: [] };

const initialize = (
  countResourcesPath = 'resources/listing/count/count.json'
): ReturnType<typeof createStore> => {
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

  cy.fixture('resources/resourceListing').then((data) => {
    cy.interceptAPIRequest({
      alias: 'resources',
      method: Method.GET,
      path: '**/resources?*',
      response: data
    });
  });

  cy.fixture(countResourcesPath).then((data) => {
    cy.interceptAPIRequest({
      alias: 'countResources',
      method: Method.GET,
      path: '**/count?*',
      response: data
    });
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

  it('deactivates the submit status button when a Resource of type anomaly detection is selected', () => {
    const store = initialize();
    store.set(selectedResourcesAtom, [anomalyDetection]);

    cy.findByLabelText(labelMoreActions).click();
    cy.findByTestId(labelSubmitStatus).should('have.attr', 'aria-disabled');
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
      cy.findByLabelText(labelStickyForAnyNonOkStatus).should('be.checked');
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
      cy.findByLabelText(labelStickyForAnyNonOkStatus).uncheck();
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
        expect(request.body).to.deep.equal({
          check: { is_forced: true },
          resources: [{ id: 0, parent: null, type: 'host' }]
        });
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
        expect(request.body).to.deep.equal({
          check: { is_forced: false },
          resources: [{ id: 0, parent: null, type: 'host' }]
        });
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

describe('CSV export', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      cy.stub(win, 'open').as('windowOpen');
    });
  });

  it('export resources with default checks when clicking the CSV export button', () => {
    initialize();
    cy.waitForRequest('@countResources');

    cy.findByRole('button', { name: 'exportCsvButton' }).click();
    cy.findByRole('dialog').as('modal').should('be.visible');
    cy.get('@modal').contains(labelExportToCSV);
    cy.get('@modal').contains(labelFilteredResources);
    cy.get('@modal').contains(labelSelectColumns);
    cy.get('@modal')
      .findByTestId(labelVisibleColumnsOnly)
      .find('input')
      .should('not.be.checked');
    cy.get('@modal')
      .findByTestId(labelAllColumns)
      .find('input')
      .should('be.checked');
    cy.get('@modal').contains(labelSelecetPages);
    cy.get('@modal')
      .findByTestId(labelCurrentPageOnly)
      .find('input')
      .should('not.be.checked');
    cy.get('@modal')
      .findByTestId(labelAllPages)
      .find('input')
      .should('be.checked');

    cy.get('@modal').contains(labelWarningExportToCsv);
    cy.get('@modal')
      .findByRole('button', { name: labelCancel })
      .should('be.enabled');
    cy.get('@modal')
      .findByRole('button', { name: labelExport })
      .should('be.enabled');

    cy.get('@modal').findByRole('button', { name: labelExport }).click();
    cy.contains(labelExportProcessingInProgress);

    const expectedUrl = `${csvExportEndpoint}?page=1&limit=10&search=${encodeURIComponent(JSON.stringify(search))}&format=${encodeURIComponent('"csv"')}&columns=${encodeURIComponent(JSON.stringify(allColumns))}&all_pages=true`;
    cy.get('@windowOpen').should(
      'be.calledWith',
      expectedUrl,
      'noopener',
      'noreferrer'
    );

    cy.makeSnapshot();
  });

  it('export resources with custom checks when clicking the CSV export button', () => {
    const store = initialize();
    store.set(selectedColumnIdsAtom, visibleColumns);
    cy.waitForRequest('@countResources');

    cy.findByRole('button', { name: 'exportCsvButton' }).click();
    cy.findByRole('dialog').as('modal').should('be.visible');
    cy.get('@modal').contains(labelExportToCSV);
    cy.get('@modal').contains(labelSelectColumns);

    cy.get('@modal')
      .findByTestId(labelVisibleColumnsOnly)
      .find('input')
      .click();
    cy.get('@modal')
      .findByTestId(labelAllColumns)
      .find('input')
      .should('not.be.checked');

    cy.get('@modal').contains(labelSelecetPages);

    cy.get('@modal').findByTestId(labelCurrentPageOnly).find('input').click();
    cy.get('@modal')
      .findByTestId(labelAllPages)
      .find('input')
      .should('not.be.checked');

    cy.get('@modal').contains(labelWarningExportToCsv);
    cy.get('@modal')
      .findByRole('button', { name: labelExport })
      .should('be.enabled');

    cy.get('@modal').findByRole('button', { name: labelExport }).click();
    cy.contains(labelExportProcessingInProgress);

    const expectedUrl = `${csvExportEndpoint}?page=1&limit=10&search=${encodeURIComponent(JSON.stringify(search))}&format=${encodeURIComponent('"csv"')}&columns=${encodeURIComponent(JSON.stringify(visibleColumns))}&all_pages=false`;
    cy.get('@windowOpen').should(
      'be.calledWith',
      expectedUrl,
      'noopener',
      'noreferrer'
    );

    cy.makeSnapshot();
  });

  it('display the warning message and disable the export button when the number of resources exceeds 10,000', () => {
    initialize('resources/listing/count/massiveCount.json');
    cy.waitForRequest('@countResources');
    cy.findByRole('button', { name: 'exportCsvButton' }).click();
    cy.findByRole('dialog').as('modal').should('be.visible');
    cy.get('@modal').contains(labelExportToCSV);
    cy.get('@modal').contains(labelWarningExportToCsv);
    cy.get('@modal').contains(labelFilterRessources);
    cy.get('@modal')
      .findByRole('button', { name: labelCancel })
      .should('be.enabled');
    cy.get('@modal')
      .findByRole('button', { name: labelExport })
      .should('be.disabled');

    cy.makeSnapshot();
  });
});
