import { Provider, createStore } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { labelPreviewRemainsEmpty } from '../../translatedLabels';
import { getPublicWidgetEndpoint } from '../../utils';

import WidgetLineChart from './LineChart';
import { graphEndpoint } from './api/endpoints';
import { Data, FormThreshold, FormTimePeriod, PanelOptions } from './models';

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

interface InitializeComponentProps
  extends Partial<
    Pick<
      PanelOptions,
      | 'curveType'
      | 'areaOpacity'
      | 'dashLength'
      | 'dashOffset'
      | 'isCenteredZero'
      | 'legendDisplayMode'
      | 'legendPlacement'
      | 'lineStyleMode'
      | 'lineWidth'
      | 'lineWidthMode'
      | 'showArea'
      | 'showAxisBorder'
      | 'showGridLines'
      | 'showLegend'
      | 'showPoints'
      | 'scale'
      | 'scaleLogarithmicBase'
      | 'gridLinesType'
      | 'displayType'
      | 'barRadius'
      | 'barOpacity'
    >
  > {
  data?: Data;
  isPublic?: boolean;
  threshold?: FormThreshold;
  timePeriod?: FormTimePeriod;
}

const initializeComponent = ({
  data = serviceMetrics,
  threshold = defaultThreshold,
  timePeriod = defaultTimePeriod,
  isPublic = false,
  ...panelOptions
}: InitializeComponentProps): void => {
  const store = createStore();
  store.set(isOnPublicPageAtom, isPublic);

  cy.viewport('macbook-13');

  cy.fixture('Widgets/Graph/lineChart.json').then((lineChart) => {
    cy.interceptAPIRequest({
      alias: 'getLineChart',
      method: Method.GET,
      path: `${graphEndpoint}**`,
      response: lineChart
    });

    cy.interceptAPIRequest({
      alias: 'getPublicWidget',
      method: Method.GET,
      path: `./api/latest${getPublicWidgetEndpoint({
        dashboardId: 1,
        playlistHash: 'hash',
        widgetId: '1'
      })}`,
      response: lineChart
    });
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <div style={{ height: '400px', width: '100%' }}>
            <WidgetLineChart
              dashboardId={1}
              globalRefreshInterval={{
                interval: null,
                type: 'global' as const
              }}
              id="1"
              panelData={data}
              panelOptions={{
                globalRefreshInterval: 30,
                refreshInterval: 'manual',
                threshold,
                timeperiod: timePeriod,
                ...panelOptions
              }}
              playlistHash="hash"
              refreshCount={0}
            />
          </div>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Public widget', () => {
  it('sends a request to the public API when the widget is displayed in a public page', () => {
    initializeComponent({ isPublic: true });

    cy.waitForRequest('@getPublicWidget');
  });
});

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

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');
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

  it('displays the line chart with a natural curve when the corresponding prop is set', () => {
    initializeComponent({ curveType: 'natural' });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the line chart with a step curve when the corresponding prop is set', () => {
    initializeComponent({ curveType: 'step' });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the line chart with a lot of custom style settings when corresponding props are set', () => {
    initializeComponent({
      areaOpacity: 10,
      curveType: 'natural',
      dashLength: 6,
      dashOffset: 10,
      gridLinesType: 'horizontal',
      isCenteredZero: true,
      legendDisplayMode: 'list',
      legendPlacement: 'right',
      lineStyleMode: 'dash',
      lineWidth: 1,
      lineWidthMode: 'custom',
      showArea: 'show',
      showAxisBorder: false,
      showGridLines: true,
      showLegend: true,
      showPoints: true
    });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the bar chart when the corresponding prop is set', () => {
    initializeComponent({
      displayType: 'bar'
    });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });

  it('displays the customised bar chart when corresponding props are set', () => {
    initializeComponent({
      barOpacity: 20,
      barRadius: 50,
      displayType: 'bar'
    });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });

  it('displays the stacked bar chart when the corresponding prop is set', () => {
    initializeComponent({
      displayType: 'bar-stacked'
    });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });

  it('displays custom stacked bar chart when corresponding props are set', () => {
    initializeComponent({
      barOpacity: 20,
      displayType: 'bar-stacked'
    });

    cy.waitForRequest('@getLineChart');

    cy.contains('cpu (%)').should('be.visible');
    cy.contains('cpu AVG (%)').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });
});
