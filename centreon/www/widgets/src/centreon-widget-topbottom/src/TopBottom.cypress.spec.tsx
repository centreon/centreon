import { createStore } from 'jotai';

import { Method } from '@centreon/ui';

import { FormThreshold } from '../../models';

import { metricsTopEndpoint } from './api/endpoint';
import { Data, TopBottomSettings } from './models';

import Widget from '.';

interface Props {
  topBottomSettings?: TopBottomSettings;
}

const defaultSettings = {
  numberOfValues: 10,
  order: 'top',
  showLabels: true
} as const;

const data: Data = {
  metrics: [
    {
      id: 2,
      name: 'rta',
      unit: 'B'
    }
  ],
  resources: [
    {
      resourceType: 'host-group',
      resources: [
        {
          id: 1,
          name: 'HG1'
        }
      ]
    },
    {
      resourceType: 'host',
      resources: [
        {
          id: 1,
          name: 'H1'
        }
      ]
    }
  ]
};

const defaultThreshold: FormThreshold = {
  criticalType: 'default',
  customCritical: 0,
  customWarning: 0,
  enabled: true,
  warningType: 'default'
};

const initializeComponent = ({
  topBottomSettings = defaultSettings
}: Props): void => {
  const store = createStore();

  cy.viewport('macbook-13');

  cy.fixture('Widgets/Graph/topBottom.json').then((topBottom) => {
    cy.interceptAPIRequest({
      alias: 'getTop',
      method: Method.GET,
      path: `${metricsTopEndpoint}**`,
      response: topBottom
    });
  });

  cy.mount({
    Component: (
      <div style={{ height: '400px', width: '100%' }}>
        <Widget
          globalRefreshInterval={30}
          panelData={data}
          panelOptions={{
            refreshInterval: 'custom',
            refreshIntervalCustom: 30,
            threshold: defaultThreshold,
            topBottomSettings,
            valueFormat: 'human'
          }}
          store={store}
        />
      </div>
    )
  });
};

describe('TopBottom', () => {
  it('displays the widget', () => {
    initializeComponent({});

    cy.waitForRequest('@getTop').then(({ request }) => {
      expect(request.url.search).to.equal(
        '?limit=10&sort_by=%7B%22current_value%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%2C%7B%22host.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D&metric_name=rta'
      );
    });

    cy.contains('#1 Centreon_server_1_Ping').should('be.visible');
    cy.contains('#2 Centreon_server_2_Ping').should('be.visible');
    cy.contains('#3 Centreon_server_3_Ping').should('be.visible');
    cy.contains('#4 Centreon_server_4_Ping').should('be.visible');

    cy.contains('10 B').should('be.visible');
    cy.contains('20 B').should('be.visible');
    cy.contains('30 B').should('be.visible');
    cy.contains('40 B').should('be.visible');

    cy.makeSnapshot();
  });

  it('retrieves the top values when specific settings are set', () => {
    initializeComponent({
      topBottomSettings: {
        numberOfValues: 5,
        order: 'bottom',
        showLabels: true
      }
    });

    cy.waitForRequest('@getTop').then(({ request }) => {
      expect(request.url.search).to.equal(
        '?limit=5&sort_by=%7B%22current_value%22%3A%22DESC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%2C%7B%22host.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D&metric_name=rta'
      );
    });
  });

  it('does not display the labels when the corresponding setting is disabled', () => {
    initializeComponent({
      topBottomSettings: {
        numberOfValues: 5,
        order: 'bottom',
        showLabels: false
      }
    });

    cy.contains('#1 Centreon_server_1_Ping').should('be.visible');
    cy.contains('#2 Centreon_server_2_Ping').should('be.visible');
    cy.contains('#3 Centreon_server_3_Ping').should('be.visible');
    cy.contains('#4 Centreon_server_4_Ping').should('be.visible');

    cy.contains('10 B').should('not.exist');
    cy.contains('20 B').should('not.exist');
    cy.contains('30 B').should('not.exist');
    cy.contains('40 B').should('not.exist');

    cy.makeSnapshot();
  });
});
