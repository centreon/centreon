import { createStore } from 'jotai';

import { Method } from '@centreon/ui';

import { Data, PanelOptions } from '../models';
import StatusGrid from '..';
import {
  labelAllMetricsAreWorkingFine,
  labelMetricName,
  labelNoResources,
  labelServiceName,
  labelValue
} from '../translatedLabels';
import { resourcesEndpoint } from '../api/endpoints';

import {
  hostOptions,
  noResources,
  resources,
  serviceOptions,
  services
} from './testUtils';

interface Props {
  data: Data;
  options: PanelOptions;
}

const initialize = ({ options, data }: Props): void => {
  const store = createStore();

  cy.mount({
    Component: (
      <div style={{ height: '100vh', width: '100vw' }}>
        <StatusGrid
          globalRefreshInterval={{
            interval: 30,
            type: 'manual'
          }}
          panelData={data}
          panelOptions={options}
          refreshCount={0}
          store={store}
        />
      </div>
    )
  });
};

const hostsRequests = (): void => {
  cy.fixture('Widgets/StatusGrid/hostResources.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getHostResources',
      method: Method.GET,
      path: `./api/latest${resourcesEndpoint}?page=1&limit=20**`,
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/hostTooltipDetails.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getHostTooltipDetails',
      method: Method.GET,
      path: `./api/latest${resourcesEndpoint}**`,
      query: { name: 'types', value: ['service'] },
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/acknowledgement.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getAcknowledgement',
      method: Method.GET,
      path: '/acknowledgements**',
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/downtime.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getDowntime',
      method: Method.GET,
      path: './api/latest/centreon/api/latest/monitoring/hosts/16/downtimes**',
      response: data
    });
  });
};

const servicesRequests = (): void => {
  cy.fixture('Widgets/StatusGrid/serviceResources.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getServiceResources',
      method: Method.GET,
      path: `./api/latest${resourcesEndpoint}?page=1&limit=20**`,
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/serviceTooltipDetails.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getServiceTooltipDetails28',
      method: Method.GET,
      path: `./api/latest/monitoring/hosts/14/services/28/metrics`,
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/serviceTooltipDetails.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getServiceTooltipDetails27',
      method: Method.GET,
      path: `./api/latest/monitoring/hosts/14/services/27/metrics`,
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/acknowledgement.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getAcknowledgement',
      method: Method.GET,
      path: './api/latest/monitoring/hosts/14/services/25/acknowledgements**',
      response: data
    });
  });
  cy.fixture('Widgets/StatusGrid/downtime.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getDowntime',
      method: Method.GET,
      path: './api/latest/monitoring/hosts/14/services/19/downtimes**',
      response: data
    });
  });
};

describe('View by host', () => {
  describe('With Resources', () => {
    beforeEach(() => {
      cy.clock(new Date(2021, 1, 1).getTime(), ['Date']);
      hostsRequests();
      initialize({ data: { resources }, options: hostOptions });
    });

    it('displays tiles', () => {
      cy.waitForRequest('@getHostResources');

      cy.contains('Centreon-Server').should('be.visible');
      cy.get('[data-status="up"]').should('be.visible');
      cy.get('[data-status="up"]')
        .parent()
        .parent()
        .should('have.css', 'background-color', 'rgb(136, 185, 34)');

      cy.contains('Passive_server_1').should('be.visible');
      cy.get('[data-status="down"]').should('be.visible');
      cy.get('[data-status="down"]')
        .parent()
        .parent()
        .should('have.css', 'background-color', 'rgb(255, 102, 102)');

      cy.contains('Passive_server').should('be.visible');
      cy.get('[data-status="unknown"]').should('be.visible');
      cy.get('[data-status="unknown"]')
        .parent()
        .parent()
        .should('have.css', 'background-color', 'rgb(240, 233, 248)');

      cy.makeSnapshot();
    });

    it('displays host informations when the mouse is over a tile', () => {
      cy.contains('Passive_server_1').should('be.visible');

      cy.contains('Passive_server_1').trigger('mouseover');

      cy.waitForRequest('@getHostTooltipDetails');
      cy.waitForRequest('@getDowntime');

      cy.get('[data-resourceName="Passive_server_1"]').should(
        'have.css',
        'color',
        'rgb(227, 227, 227)'
      );

      cy.contains(labelServiceName).should('be.visible');

      cy.get('[data-serviceName="Passive_server"]').should(
        'have.css',
        'color',
        'rgb(255, 102, 102)'
      );

      cy.contains('unknown (No output returned from host check)').should(
        'be.visible'
      );
      cy.contains(
        'In downtime (from November 17, 2023 6:11 PM to November 17, 2023 7:11 PM)'
      ).should('be.visible');
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Without Resources', () => {
    beforeEach(() =>
      initialize({ data: { resources: noResources }, options: hostOptions })
    );

    it('displays a no resources message', () => {
      cy.contains(labelNoResources).should('be.visible');

      cy.makeSnapshot();
    });
  });
});

describe('View by service', () => {
  describe('With Resources', () => {
    beforeEach(() => {
      cy.clock(new Date(2021, 1, 1).getTime(), ['Date']);
      servicesRequests();
      initialize({ data: { resources }, options: serviceOptions });
    });

    it('displays tiles', () => {
      cy.waitForRequest('@getServiceResources');

      services.forEach(({ name, status, color, eq }) => {
        cy.contains(name).should('be.visible');
        cy.get(`[data-status="${status}"]`).eq(eq).should('be.visible');
        cy.get(`[data-status="${status}"]`)
          .eq(eq)
          .parent()
          .parent()
          .should('have.css', 'background-color', color);
      });

      cy.makeSnapshot();
    });

    it('displays service informations when an ok service tile is hovered', () => {
      cy.contains('Ping').trigger('mouseover');

      cy.get('[data-resourcename="Ping"]').should(
        'have.css',
        'color',
        'rgb(136, 185, 34)'
      );
      cy.get('[data-parentstatus="5"]').should('be.visible');
      cy.findAllByText('Centreon-Server').should('have.length', 7);
      cy.contains(labelAllMetricsAreWorkingFine).should('be.visible');
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays service informations when a critical service tile is hovered', () => {
      cy.contains('Centreon_Pass').trigger('mouseover');

      cy.waitForRequest('@getServiceTooltipDetails28');

      cy.get('[data-resourcename="Centreon_Pass"]').should(
        'have.css',
        'color',
        'rgb(255, 102, 102)'
      );
      cy.get('[data-parentstatus="5"]').should('be.visible');
      cy.findAllByText('Centreon-Server').should('have.length', 7);
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.contains(labelMetricName).should('be.visible');
      cy.contains(labelValue).should('be.visible');

      cy.contains('rta').should('be.visible');
      cy.contains('1').should('have.css', 'color', 'rgb(253, 155, 39)');

      cy.makeSnapshot();
    });

    it('displays service informations when a warning service tile is hovered', () => {
      cy.contains('Passive').trigger('mouseover');

      cy.waitForRequest('@getServiceTooltipDetails27');

      cy.get('[data-resourcename="Passive"]').should(
        'have.css',
        'color',
        'rgb(253, 155, 39)'
      );
      cy.get('[data-parentstatus="5"]').should('be.visible');
      cy.findAllByText('Centreon-Server').should('have.length', 7);
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.contains(labelMetricName).should('be.visible');
      cy.contains(labelValue).should('be.visible');

      cy.contains('rta').should('be.visible');
      cy.contains('1').should('have.css', 'color', 'rgb(253, 155, 39)');

      cy.makeSnapshot();
    });

    it('displays service informations when an acknowledged service tile is hovered', () => {
      cy.contains('Memory').trigger('mouseover');

      cy.get('[data-resourcename="Memory"]').should(
        'have.css',
        'color',
        'rgb(227, 227, 227)'
      );
      cy.get('[data-parentstatus="5"]').should('be.visible');
      cy.findAllByText('Centreon-Server').should('have.length', 7);
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.contains('unknown (Execute command failed)').should('be.visible');
      cy.contains('Acknowledged (Acknowledged by admin)').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays service informations when a service in downtime tile is hovered', () => {
      cy.contains('Disk-/').trigger('mouseover');

      cy.get('[data-resourcename="Disk-/"]').should(
        'have.css',
        'color',
        'rgb(227, 227, 227)'
      );
      cy.get('[data-parentstatus="5"]').should('be.visible');
      cy.findAllByText('Centreon-Server').should('have.length', 7);
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.contains('unknown (Execute command failed)').should('be.visible');
      cy.contains(
        'In downtime (from November 17, 2023 6:11 PM to November 17, 2023 7:11 PM)'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays service informations when an unknown service tile is hovered', () => {
      cy.contains('Load').trigger('mouseover');

      cy.get('[data-resourcename="Load"]').should(
        'have.css',
        'color',
        'rgb(227, 227, 227)'
      );
      cy.get('[data-parentstatus="5"]').should('be.visible');
      cy.findAllByText('Centreon-Server').should('have.length', 7);
      cy.contains('February 1, 2021 12:00 AM').should('be.visible');

      cy.contains('unknown (Execute command failed)').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Without Resources', () => {
    beforeEach(() =>
      initialize({ data: { resources: noResources }, options: serviceOptions })
    );

    it('displays a no resources message', () => {
      cy.contains(labelNoResources).should('be.visible');

      cy.makeSnapshot();
    });
  });
});
