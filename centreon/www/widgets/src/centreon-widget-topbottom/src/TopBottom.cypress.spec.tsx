import { createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { Method } from '@centreon/ui';

import { Data, FormThreshold } from '../../models';
import { labelPreviewRemainsEmpty } from '../../translatedLabels';

import { metricsTopEndpoint } from './api/endpoint';
import { TopBottomSettings } from './models';

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
      name: 'C:#storage',
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

const linkToResourcePing1 =
  '/monitoring/resources?details=%7B%22id%22%3A%221%22%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2F1%2Fservices%2F1%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22h1-s1%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbPing_1%5C%5Cb%22%2C%22name%22%3A%22Ping_1%22%7D%5D%7D%2C%7B%22name%22%3A%22h.name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbCentreon_server%5C%5Cb%22%2C%22name%22%3A%22Centreon_server%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';

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
        <BrowserRouter>
          <Widget
            globalRefreshInterval={{
              interval: 30,
              type: 'global'
            }}
            panelData={data}
            panelOptions={{
              refreshInterval: 'custom',
              refreshIntervalCustom: 30,
              threshold: defaultThreshold,
              topBottomSettings,
              valueFormat: 'human'
            }}
            refreshCount={0}
            store={store}
          />
        </BrowserRouter>
      </div>
    )
  });
};

const initializeEmptyComponent = (): void => {
  const store = createStore();

  cy.viewport('macbook-13');

  cy.mount({
    Component: (
      <div style={{ height: '400px', width: '100%' }}>
        <BrowserRouter>
          <Widget
            globalRefreshInterval={{
              interval: 30,
              type: 'global'
            }}
            panelData={{}}
            panelOptions={{
              refreshInterval: 'custom',
              refreshIntervalCustom: 30,
              threshold: defaultThreshold,
              topBottomSettings: defaultSettings,
              valueFormat: 'human'
            }}
            refreshCount={0}
            store={store}
          />
        </BrowserRouter>
      </div>
    )
  });
};

describe('TopBottom', () => {
  it('displays a message when the dataset is empty', () => {
    initializeEmptyComponent();
    cy.contains(labelPreviewRemainsEmpty).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the widget', () => {
    initializeComponent({});

    cy.waitForRequest('@getTop').then(({ request }) => {
      expect(request.url.search).to.equal(
        '?limit=10&sort_by=%7B%22current_value%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%2C%7B%22host.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D&metric_name=C%3A%23storage'
      );
    });

    cy.contains('#1 Centreon_server_Ping_1').should('be.visible');
    cy.contains('#2 Centreon_server_Ping_2').should('be.visible');
    cy.contains('#3 Centreon_server_Ping_3').should('be.visible');
    cy.contains('#4 Centreon_server_Ping_4').should('be.visible');

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
        '?limit=5&sort_by=%7B%22current_value%22%3A%22DESC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%2C%7B%22host.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D&metric_name=C%3A%23storage'
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

    cy.contains('#1 Centreon_server_Ping_1').should('be.visible');
    cy.contains('#2 Centreon_server_Ping_2').should('be.visible');
    cy.contains('#3 Centreon_server_Ping_3').should('be.visible');
    cy.contains('#4 Centreon_server_Ping_4').should('be.visible');

    cy.contains('10 B').should('not.exist');
    cy.contains('20 B').should('not.exist');
    cy.contains('30 B').should('not.exist');
    cy.contains('40 B').should('not.exist');

    cy.makeSnapshot();
  });

  it('navigates to the resource with predefined filters when resource name or bar is clicked', () => {
    initializeComponent({
      topBottomSettings: {
        numberOfValues: 5,
        order: 'bottom',
        showLabels: false
      }
    });

    cy.findAllByTestId('link to Ping_1')
      .eq(0)
      .should('have.attr', 'href', linkToResourcePing1);

    cy.findAllByTestId('link to Ping_1')
      .eq(1)
      .should('have.attr', 'href', linkToResourcePing1);
  });
});
