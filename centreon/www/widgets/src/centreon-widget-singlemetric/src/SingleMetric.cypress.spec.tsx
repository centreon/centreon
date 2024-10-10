import { Provider, createStore } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { Data } from '../../models';
import { labelPreviewRemainsEmpty } from '../../translatedLabels';
import { getPublicWidgetEndpoint } from '../../utils';

import Graph from './Graph';
import { graphEndpoint } from './api/endpoints';
import { FormThreshold, ValueFormat } from './models';

const panelData: Data = {
  metrics: [
    {
      id: 1,
      name: 'Ping_1',
      unit: 'ms'
    },
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

const diskUsedMetricData: Data = {
  metrics: [
    {
      id: 1,
      metrics: [
        {
          id: 1,
          name: 'disk_used',
          unit: 'B'
        }
      ],
      name: 'Disk'
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

const emptyServiceMetrics: Data = {
  metrics: [],
  resources: []
};

const metaServiceData: Data = {
  metrics: [],
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

interface Props {
  data?: Data;
  fixture?: string;
  isPublic?: boolean;
  options?: {
    displayType: 'text' | 'gauge' | 'bar';
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    threshold: FormThreshold;
    valueFormat: ValueFormat;
  };
}

const initializeComponent = ({
  data = panelData,
  options = {
    displayType: 'text',
    refreshInterval: 'default',
    threshold: defaultThreshold,
    valueFormat: 'human'
  },
  fixture = 'Widgets/Graph/lineChart.json',
  isPublic = false
}: Props): void => {
  const store = createStore();
  store.set(isOnPublicPageAtom, isPublic);

  cy.viewport('macbook-11');

  cy.fixture(fixture).then((lineChart) => {
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

  const panelOptions = {
    ...options,
    refreshInterval: 'default',
    refreshIntervalCustom: 15
  };

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <div style={{ height: '400px', width: '100%' }}>
            <Graph
              globalRefreshInterval={{
                interval: null,
                type: 'global'
              }}
              refreshCount={0}
              {...data}
              {...panelOptions}
              dashboardId={1}
              id="1"
              isFromPreview={false}
              playlistHash="hash"
            />
          </div>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Public widget', () => {
  it('sends a request to the public API when the widget is displayed in a public page', () => {
    initializeComponent({
      isPublic: true,
      options: {
        displayType: 'text',
        threshold: defaultThreshold,
        valueFormat: 'human'
      }
    });

    cy.waitForRequest('@getPublicWidget');
  });
});

describe('Single metric Widget', () => {
  it('displays a message when the widget has no metric', () => {
    initializeComponent({
      data: emptyServiceMetrics,
      options: {
        displayType: 'text',
        threshold: disabledThreshold
      }
    });
    cy.contains(labelPreviewRemainsEmpty).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Text', () => {
    it('displays the metric value as success and thresholds when thresholds are enabled', () => {
      initializeComponent({
        options: {
          displayType: 'text',
          threshold: defaultThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('34%').should('have.css', 'color', 'rgb(136, 185, 34)');
      cy.contains('Warning: 65% - 70%').should(
        'have.css',
        'color',
        'rgb(253, 155, 39)'
      );
      cy.contains('Critical: 85% - 90%').should(
        'have.css',
        'color',
        'rgb(255, 74, 74)'
      );

      cy.makeSnapshot();
    });

    it('displays the metric value with the default color when thresholds are disabled', () => {
      initializeComponent({
        options: {
          displayType: 'text',
          threshold: disabledThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('34%').should('have.css', 'color', 'rgb(46, 104, 170)');
      cy.contains('Warning: 70%').should('not.exist');
      cy.contains('Critical: 90%').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the metric value as warning when the warning threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'text',
          threshold: warningThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('34%').should('have.css', 'color', 'rgb(253, 155, 39)');

      cy.makeSnapshot();
    });

    it('displays the metric value as critical when the critical threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'text',
          threshold: criticalThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('34%').should('have.css', 'color', 'rgb(255, 74, 74)');
      cy.contains('Warning: 10%').should('be.visible');
      cy.contains('Critical: 20%').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value as critical when the critical threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'text',
          threshold: criticalThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('34%').should('have.css', 'color', 'rgb(255, 74, 74)');
      cy.contains('Warning: 10%').should('be.visible');
      cy.contains('Critical: 20%').should('be.visible');

      cy.makeSnapshot();
    });

    it('display the metric value as human readable', () => {
      initializeComponent({
        data: diskUsedMetricData,
        fixture: 'Widgets/Graph/chartWithBytes.json',
        options: {
          displayType: 'text',
          threshold: defaultThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('332.06 KB').should('be.visible');
    });

    it('display the metric value as raw', () => {
      initializeComponent({
        data: diskUsedMetricData,
        fixture: 'Widgets/Graph/chartWithBytes.json',
        options: {
          displayType: 'text',
          threshold: defaultThreshold,
          valueFormat: 'raw'
        }
      });

      cy.contains('340032.4232 B').should('be.visible');
    });
  });

  describe('Single bar', () => {
    it('displays the metric value with the default color and thresholds when thresholds are enabled', () => {
      initializeComponent({
        options: {
          displayType: 'bar',
          threshold: defaultThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-bar-#88B922').should('be.visible');

      cy.findByTestId('warning-line-65-tooltip').trigger('mouseover');
      cy.contains(
        'Warning threshold: 65%. Value defined by {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('warning-line-70-tooltip').trigger('mouseover');
      cy.contains(
        'Warning threshold: 70%. Value defined by {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('critical-line-85-tooltip').trigger('mouseover');
      cy.contains(
        'Critical threshold: 85%. Value defined by {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('critical-line-90-tooltip').trigger('mouseover');
      cy.contains(
        'Critical threshold: 90%. Value defined by {{metric}} metric'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value with the default color when thresholds are disabled', () => {
      initializeComponent({
        options: {
          displayType: 'bar',
          threshold: disabledThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(46, 104, 170)');
      cy.findByTestId('34-bar-#2E68AA').should('be.visible');

      cy.findByTestId('warning-line-70').should('not.exist');
      cy.findByTestId('critical-line-90').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the metric value as warning when the warning threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'bar',
          threshold: warningThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(253, 155, 39)');
      cy.findByTestId('34-bar-#FD9B27').should('be.visible');
      cy.findByTestId('34-bar-#FD9B27').should(
        'have.css',
        'width',
        '465.69696044921875px'
      );

      cy.makeSnapshot();
    });

    it('displays the metric value as critical when the critical threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'bar',
          threshold: criticalThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(255, 74, 74)');
      cy.findByTestId('34-bar-#FF4A4A').should('be.visible');
      cy.findByTestId('34-bar-#FF4A4A').should('have.css', 'width', '1356px');

      cy.findByTestId('warning-line-10-tooltip').trigger('mouseover');
      cy.contains('Warning threshold: 10%. Custom value').should('be.visible');

      cy.findByTestId('critical-line-20-tooltip').trigger('mouseover');
      cy.contains('Critical threshold: 20%. Custom value').should('be.visible');

      cy.makeSnapshot();
    });

    it('display the metric value as human readable', () => {
      initializeComponent({
        data: diskUsedMetricData,
        fixture: 'Widgets/Graph/chartWithBytes.json',
        options: {
          displayType: 'bar',
          threshold: defaultThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('332.06 KB').should('be.visible');
    });

    it('display the metric value as raw', () => {
      initializeComponent({
        data: diskUsedMetricData,
        fixture: 'Widgets/Graph/chartWithBytes.json',
        options: {
          displayType: 'bar',
          threshold: defaultThreshold,
          valueFormat: 'raw'
        }
      });

      cy.contains('340032.4232 B').should('be.visible');
    });
  });

  describe('Gauge', () => {
    it('displays the metric value as success and thresholds when thresholds are enabled', () => {
      initializeComponent({
        options: {
          displayType: 'gauge',
          threshold: defaultThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-arc').should('have.attr', 'fill', '#88B922');

      cy.findAllByTestId('5-arc').should('have.length', 2);

      cy.findAllByTestId('5-arc').eq(0).trigger('mouseover');
      cy.contains(
        'Warning threshold: 65%. Value defined by {{metric}} metric'
      ).should('be.visible');
      cy.contains(
        'Warning threshold: 70%. Value defined by {{metric}} metric'
      ).should('be.visible');
      cy.findAllByTestId('5-arc').eq(0).trigger('mouseleave');

      cy.findAllByTestId('5-arc').eq(1).trigger('mouseover');
      cy.contains(
        'Critical threshold: 85%. Value defined by {{metric}} metric'
      ).should('be.visible');
      cy.contains(
        'Critical threshold: 90%. Value defined by {{metric}} metric'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value with the default color when thresholds are disabled', () => {
      initializeComponent({
        options: {
          displayType: 'gauge',
          threshold: disabledThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(46, 104, 170)');
      cy.findByTestId('34-arc').should('have.attr', 'fill', '#2E68AA');

      cy.findAllByTestId('5-arc').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the metric value as warning when the warning threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'gauge',
          threshold: warningThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(253, 155, 39)');
      cy.findByTestId('34-arc').should('have.attr', 'fill', '#FD9B27');

      cy.makeSnapshot();
    });

    it('displays the metric value as critical when the critical threshold is customized', () => {
      initializeComponent({
        options: {
          displayType: 'gauge',
          threshold: criticalThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(255, 74, 74)');
      cy.findAllByTestId('34-arc').eq(1).should('have.attr', 'fill', '#FF4A4A');

      cy.findByTestId('24-arc').should('have.attr', 'fill', '#FD9B27');
      cy.findByTestId('14-arc').should('have.attr', 'fill', '#FF4A4A');

      cy.makeSnapshot();
    });

    it('display the metric value as human readable', () => {
      initializeComponent({
        data: diskUsedMetricData,
        fixture: 'Widgets/Graph/chartWithBytes.json',
        options: {
          displayType: 'gauge',
          threshold: defaultThreshold,
          valueFormat: 'human'
        }
      });

      cy.contains('332.06 KB').should('be.visible');
    });

    it('display the metric value as raw', () => {
      initializeComponent({
        data: diskUsedMetricData,
        fixture: 'Widgets/Graph/chartWithBytes.json',
        options: {
          displayType: 'gauge',
          threshold: defaultThreshold,
          valueFormat: 'raw'
        }
      });

      cy.contains('340032.4232 B').should('be.visible');
    });
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
});
