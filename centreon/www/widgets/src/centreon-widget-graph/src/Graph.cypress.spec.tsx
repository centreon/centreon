import { createStore } from 'jotai';

import { Method } from '@centreon/ui';

import { labelPreviewRemainsEmpty } from '../../translatedLabels';

import { graphEndpoint } from './api/endpoints';
import { Data, FormThreshold, FormTimePeriod } from './models';

import Widget from '.';

const serviceMetrics: Data = {
  metrics: [
    {
      id: 1,
      name: 'cpu',
      unit: '%'
    },
    {
      id: 2,
      name: 'cpu AVG',
      unit: '%'
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
    }
  ]
};

const emptyServiceMetrics: Data = {
  metrics: [],
  resources: []
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

const criticalThreshold: FormThreshold = {
  criticalType: 'custom',
  customCritical: 20,
  customWarning: 10,
  enabled: true,
  warningType: 'custom'
};

const warningThreshold: FormThreshold = {
  criticalType: 'default',
  customCritical: 0,
  customWarning: 20,
  enabled: true,
  warningType: 'custom'
};

const defaultTimePeriod: FormTimePeriod = {
  timePeriodType: 1
};

const customTimePeriod: FormTimePeriod = {
  end: '2021-09-02T00:00:00.000Z',
  start: '2021-09-01T00:00:00.000Z',
  timePeriodType: -1
};

interface InitializeComponentProps {
  data?: Data;
  graphDataPath?: string;
  threshold?: FormThreshold;
  timePeriod?: FormTimePeriod;
}

const initializeComponent = ({
  data = serviceMetrics,
  threshold = defaultThreshold,
  timePeriod = defaultTimePeriod,
  graphDataPath = 'Widgets/Graph/lineChart.json'
}: InitializeComponentProps): void => {
  const store = createStore();

  cy.viewport('macbook-13');

  cy.fixture(graphDataPath).then((lineChart) => {
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
        <Widget
          globalRefreshInterval={{
            interval: null,
            type: 'global' as const
          }}
          panelData={data}
          panelOptions={{
            globalRefreshInterval: 30,
            refreshInterval: 'manual',
            threshold,
            timeperiod: timePeriod
          }}
          refreshCount={0}
          store={store}
        />
      </div>
    )
  });
};

describe('Graph Widget', () => {
  it('displays a message when the widget has no metric', () => {
    initializeComponent({ data: emptyServiceMetrics });
    cy.contains(labelPreviewRemainsEmpty).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the line chart when the widget has metrics', () => {
    initializeComponent({});

    cy.waitForRequest('@getLineChart').then(({ request }) => {
      expect(request.url.search).to.include('metric_names=[cpu,cpu%20AVG]');
      expect(request.url.search).to.include(
        'search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D'
      );
    });

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');
    cy.findByTestId('warning-line-65').should('be.visible');
    cy.findByTestId('warning-line-70').should('be.visible');
    cy.findByTestId('critical-line-85').should('be.visible');
    cy.findByTestId('critical-line-90').should('be.visible');

    cy.findByTestId('warning-line-65-tooltip').trigger('mouseover');
    cy.contains('Warning threshold: 65%. Value defined by {{metric}} metric');
    cy.findByTestId('warning-line-70-tooltip').trigger('mouseover');
    cy.contains('Warning threshold: 70%. Value defined by {{metric}} metric');

    cy.findByTestId('critical-line-85-tooltip').trigger('mouseover');
    cy.contains('Critical threshold: 85%. Value defined by {{metric}} metric');
    cy.findByTestId('critical-line-90-tooltip').trigger('mouseover');
    cy.contains('Critical threshold: 90%. Value defined by {{metric}} metric');

    cy.makeSnapshot();
  });

  it('displays the line chart without thresholds when thresholds are disabled', () => {
    initializeComponent({ threshold: disabledThreshold });

    cy.findByTestId('warning-line-65').should('not.exist');
    cy.findByTestId('warning-line-70').should('not.exist');
    cy.findByTestId('critical-line-85').should('not.exist');
    cy.findByTestId('critical-line-90').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the line chart with customized warning threshold', () => {
    initializeComponent({ threshold: warningThreshold });

    cy.findByTestId('warning-line-20').should('be.visible');

    cy.findByTestId('warning-line-20-tooltip').trigger('mouseover');
    cy.contains('Warning threshold: 20%. Custom value');

    cy.makeSnapshot();
  });

  it('displays the line chart with customized critical threshold', () => {
    initializeComponent({ threshold: criticalThreshold });

    cy.findByTestId('warning-line-10').should('be.visible');
    cy.findByTestId('critical-line-20').should('be.visible');

    cy.findByTestId('critical-line-20-tooltip').trigger('mouseover');
    cy.contains('Critical threshold: 20%. Custom value');

    cy.makeSnapshot();
  });

  it('displays the line chart with a custom time period', () => {
    initializeComponent({ timePeriod: customTimePeriod });

    cy.waitForRequest('@getLineChart').then(({ request }) => {
      expect(request.url.search).to.include('start=2021-09-01T00:00:00.000Z');
      expect(request.url.search).to.include('end=2021-09-02T00:00:00.000Z');
    });
  });

  it(`displays the legend with a scrollbar when there are numerous metrics to show.`, () => {
    cy.fixture(
      'Widgets/Graph/legend/serviceMetricsForScrollableLegend.json'
    ).then((data) => {
      initializeComponent({
        data,
        graphDataPath: 'Widgets/Graph/legend/lineChartForScrollableLegend.json'
      });
    });
    cy.waitForRequest('@getLineChart');
    cy.get('path').its('length').should('eq', 100);

    cy.get('[class$="legend"]').as('legendContainer');
    cy.get('@legendContainer').should('have.css', 'overflow-Y', 'auto');
    cy.get('@legendContainer').should('have.css', 'overflow-X', 'hidden');

    cy.findByText('Legend 1 Centreon-Server').should('exist');

    cy.get('@legendContainer').scrollTo('bottom');

    cy.findByText('Legend 99 Centreon-Server').should('exist');
    cy.makeSnapshot();
  });
});
