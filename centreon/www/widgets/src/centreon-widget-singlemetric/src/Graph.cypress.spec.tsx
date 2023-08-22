import { createStore } from 'jotai';

import { Method } from '@centreon/ui';

import { Data, FormThreshold } from './models';
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

const disabledThreshold: FormThreshold = {
  criticalType: 'default',
  customCritical: 0,
  customWarning: 0,
  enabled: false,
  warningType: 'default'
};

const defaultThreshold: FormThreshold = {
  criticalType: 'default',
  customCritical: 0,
  customWarning: 0,
  enabled: true,
  warningType: 'default'
};

const emptyServiceMetrics: Data = {
  metrics: []
};

interface Props {
  data?: Data;
  options?: {
    singleMetricGraphType: 'text' | 'gauge' | 'bar';
    threshold: FormThreshold;
  };
}

const initializeComponent = ({
  data = serviceMetrics,
  options = defaultThreshold
}: Props): void => {
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
    initializeComponent({ data: emptyServiceMetrics });
    cy.contains(labelNoDataFound).should('be.visible');

    cy.matchImageSnapshot();
  });
});
