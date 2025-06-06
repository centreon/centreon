import { Provider, createStore } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { labelPreviewRemainsEmpty } from '../../translatedLabels';
import { getPublicWidgetEndpoint } from '../../utils';

import { equals } from 'ramda';
import WidgetLineChart from './LineChart';
import { graphEndpoint } from './api/endpoints';
import type {
  Data,
  FormThreshold,
  FormTimePeriod,
  PanelOptions
} from './models';

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

const metaServiceData: Data = {
  metrics: [
    {
      id: 1,
      name: 'free',
      unit: ''
    }
  ],
  resources: [
    {
      resourceType: 'meta-service',
      resources: [
        {
          id: 1,
          name: 'M1'
        }
      ]
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

const legendPositions = ['left', 'bottom', 'right'] as const;

const legendProperties = [
  { mode: 'list' as const, positions: legendPositions },
  { mode: 'grid' as const, positions: legendPositions }
];

const legendData = [
  {
    resourcesType: 'host',
    graphDataPath: 'Widgets/Graph/legend/lineChartWithRedundantHostName.json'
  },
  {
    resourcesType: 'service',
    graphDataPath: 'Widgets/Graph/legend/lineChartWithRedundantServiceName.json'
  },
  {
    resourcesType: 'host and service',
    graphDataPath:
      'Widgets/Graph/legend/lineChartWithRedundantHostAndServiceName.json'
  }
];

const checkLegendHeader = () => {
  cy.findByLabelText('cpu').contains('cpu').should('be.visible');
  cy.findByLabelText('cpu').contains('%').should('be.visible');

  cy.findByLabelText('cpu: avg').contains('cpu: avg').should('be.visible');
  cy.findByLabelText('cpu: avg').contains('%').should('be.visible');
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
  graphDataPath?: string;
}

const initializeComponent = ({
  data = serviceMetrics,
  threshold = defaultThreshold,
  timePeriod = defaultTimePeriod,
  isPublic = false,
  graphDataPath = 'Widgets/Graph/lineChart.json',
  ...panelOptions
}: InitializeComponentProps): void => {
  const store = createStore();
  store.set(isOnPublicPageAtom, isPublic);

  cy.viewport('macbook-13');

  cy.fixture(graphDataPath).then((lineChart) => {
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
      expect(request.url.search).to.include('metric_names[]=cpu');
      expect(request.url.search).to.include('metric_names[]=cpu%20AVG');
      expect(request.url.search).to.include(
        'search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D'
      );
    });

    checkLegendHeader();

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
    cy.waitForRequest('@getLineChart');

    checkLegendHeader();
    cy.findByTestId('warning-line-65').should('not.exist');
    cy.findByTestId('warning-line-70').should('not.exist');
    cy.findByTestId('critical-line-85').should('not.exist');
    cy.findByTestId('critical-line-90').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the line chart with customized warning threshold', () => {
    initializeComponent({ threshold: warningThreshold });
    cy.waitForRequest('@getLineChart');

    cy.findByTestId('warning-line-20').should('be.visible');

    cy.findByTestId('warning-line-20-tooltip').trigger('mouseover');
    cy.contains('Warning threshold: 20%. Custom value');

    cy.makeSnapshot();
  });

  it('displays the line chart with customized critical threshold', () => {
    initializeComponent({ threshold: criticalThreshold });
    cy.waitForRequest('@getLineChart');

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

    checkLegendHeader();

    cy.makeSnapshot();
  });

  it('displays the line chart with a step curve when the corresponding prop is set', () => {
    initializeComponent({ curveType: 'step' });

    cy.waitForRequest('@getLineChart');

    checkLegendHeader();

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

    checkLegendHeader();

    cy.makeSnapshot();
  });

  it('displays the bar chart when the corresponding prop is set', () => {
    initializeComponent({
      displayType: 'bar'
    });

    cy.waitForRequest('@getLineChart');

    checkLegendHeader();

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

    checkLegendHeader();

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });

  it('displays the stacked bar chart when the corresponding prop is set', () => {
    initializeComponent({
      displayType: 'bar-stacked'
    });

    cy.waitForRequest('@getLineChart');

    checkLegendHeader();

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });

  it('displays custom stacked bar chart when corresponding props are set', () => {
    initializeComponent({
      barOpacity: 20,
      displayType: 'bar-stacked'
    });

    cy.waitForRequest('@getLineChart');
    checkLegendHeader();

    cy.findByTestId('stacked-bar-1-0-40').should('have.attr', 'opacity');

    cy.makeSnapshot();
  });

  it('sends a request with meta-service when the corresponding data is provided', () => {
    initializeComponent({
      data: metaServiceData
    });

    cy.waitForRequest('@getLineChart').then(({ request }) => {
      const searchParameters = request.url.searchParams;

      expect(searchParameters.get('search')).to.equal(
        '{"$and":[{"metaservice.id":{"$in":[1]}}]}'
      );
      expect(searchParameters.get('metrics_names')).to.equal(null);
    });
  });

  legendProperties.forEach(({ mode, positions }) => {
    positions.forEach((position) => {
      it(`displays the legend with a scrollbar for placement: ${position} and mode: ${mode}.`, () => {
        cy.fixture(
          'Widgets/Graph/legend/serviceMetricsForScrollableLegend.json'
        ).then((data) => {
          initializeComponent({
            showLegend: true,
            legendDisplayMode: mode,
            legendPlacement: position,
            data,
            graphDataPath:
              'Widgets/Graph/legend/lineChartForScrollableLegend.json'
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
        cy.makeSnapshot(
          `legend with a scrollbar for placement: ${position} and mode: ${mode}.`
        );
      });
    });
  });

  legendData.forEach(({ resourcesType, graphDataPath }) => {
    it(`do not display the ${resourcesType} name from the legend and tooltip when it\'s redundant`, () => {
      initializeComponent({
        showLegend: true,
        graphDataPath
      });
      cy.waitForRequest('@getLineChart');

      cy.get('[class$="legend"]').as('legendContainer');

      cy.get('path[data-metric=1]').realHover();
      cy.findByRole('tooltip').as('tooltip');

      cy.fixture(graphDataPath).then((data) => {
        data.metrics.forEach(({ host_name, service_name, metric }) => {
          if (equals(resourcesType, 'host')) {
            cy.get('@legendContainer').should('not.contain', host_name);
            cy.get('@legendContainer').should('contain', metric);
            cy.get('@tooltip').should('not.contain', host_name);
            cy.get('@tooltip').should('contain', metric);

            return;
          }
          if (equals(resourcesType, 'service')) {
            cy.get('@legendContainer').should('not.contain', service_name);
            cy.get('@legendContainer').should('contain', metric);

            cy.get('@tooltip').should('not.contain', service_name);
            cy.get('@tooltip').should('contain', metric);

            return;
          }
          cy.get('@legendContainer').should('not.contain', host_name);
          cy.get('@legendContainer').should('not.contain', service_name);
          cy.get('@legendContainer').should('contain', metric);

          cy.get('@tooltip').should('not.contain', host_name);
          cy.get('@tooltip').should('not.contain', service_name);
          cy.get('@tooltip').should('contain', metric);
        });

        cy.makeSnapshot(
          `do not display the ${resourcesType} name from the legend and tooltip when it\'s redundant`
        );
      });
    });
  });
});
