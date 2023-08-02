import { createStore } from 'jotai';

import { Method } from '@centreon/ui';

import { Data } from './models';
import { labelNoDataFound } from './translatedLabels';
import { graphEndpoint } from './api/endpoints';

import Widget from '.';

const serviceMetrics: Data = {
  metrics: [
    {
      id: 1,
      metrics: [
        {
          id: 1,
          name: 'Ping_1',
          unit: 'ms'
        }
      ],
      name: 'Ping'
    },
    {
      id: 2,
      metrics: [
        {
          id: 2,
          name: 'Cpu 1',
          unit: '%'
        },
        {
          id: 3,
          name: 'Cpu 2',
          unit: '%'
        }
      ],
      name: 'Cpu'
    }
  ]
};

const emptyServiceMetrics: Data = {
  metrics: []
};

const initializeComponent = (data: Data = serviceMetrics): void => {
  const store = createStore();

  cy.viewport('macbook-11');

  cy.fixture('Widgets/Graph/lineChart.json').then((lineChart) => {
    cy.interceptAPIRequest({
      alias: 'getLineChart',
      method: Method.GET,
      path: `${graphEndpoint}**`,
      response: lineChart
    });
  });

  cy.mount({
    Component: (
      <div style={{ height: '400px', width: '100%' }}>
        <Widget panelData={data} store={store} />
      </div>
    )
  });
};

describe('Graph Widget', () => {
  it('displays a message when the widget has no metric', () => {
    initializeComponent(emptyServiceMetrics);
    cy.contains(labelNoDataFound).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('displays the line chart when the widget has metrics', () => {
    initializeComponent();

    cy.waitForRequest('@getLineChart').then(({ request }) => {
      expect(request.url.search).to.include('metricIds=1,2,3');
    });

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.matchImageSnapshot();
  });
});
