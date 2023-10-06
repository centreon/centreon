import { createStore } from 'jotai';

import { Method } from '@centreon/ui';

import { Data, FormThreshold, ValueFormat } from './models';
import { labelNoDataFound } from './translatedLabels';
import { graphEndpoint } from './api/endpoints';

import Widget from '.';

const panelData: Data = {
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

interface Props {
  data?: Data;
  fixture?: string;
  options?: {
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    singleMetricGraphType: 'text' | 'gauge' | 'bar';
    threshold: FormThreshold;
    valueFormat: ValueFormat;
  };
}

const initializeComponent = ({
  data = panelData,
  options = {
    refreshInterval: 'default',
    singleMetricGraphType: 'text',
    threshold: defaultThreshold,
    valueFormat: 'human'
  },
  fixture = 'Widgets/Graph/lineChart.json'
}: Props): void => {
  const store = createStore();

  cy.viewport('macbook-11');

  cy.fixture(fixture).then((lineChart) => {
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
            type: 'global'
          }}
          panelData={data}
          panelOptions={{
            ...options,
            refreshInterval: 'default',
            refreshIntervalCustom: 15
          }}
          refreshCount={0}
          store={store}
        />
      </div>
    )
  });
};

describe('Single metric Widget', () => {
  it('displays a message when the widget has no metric', () => {
    initializeComponent({
      data: emptyServiceMetrics,
      options: {
        singleMetricGraphType: 'text',
        threshold: disabledThreshold
      }
    });
    cy.contains(labelNoDataFound).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Text', () => {
    it('displays the metric value as success and thresholds when thresholds are enabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'text',
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
          singleMetricGraphType: 'bar',
          threshold: defaultThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-bar-#88B922').should('be.visible');

      cy.findByTestId('warning-line-65-tooltip').trigger('mouseover');
      cy.contains(
        'Warning threshold: 65%. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('warning-line-70-tooltip').trigger('mouseover');
      cy.contains(
        'Warning threshold: 70%. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('critical-line-85-tooltip').trigger('mouseover');
      cy.contains(
        'Critical threshold: 85%. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('critical-line-90-tooltip').trigger('mouseover');
      cy.contains(
        'Critical threshold: 90%. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value with the default color when thresholds are disabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'bar',
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
          singleMetricGraphType: 'bar',
          threshold: warningThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(253, 155, 39)');
      cy.findByTestId('34-bar-#FD9B27').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value as critical when the critical threshold is customized', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'bar',
          threshold: criticalThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(255, 74, 74)');
      cy.findByTestId('34-bar-#FF4A4A').should('be.visible');
      cy.findByTestId('34-bar-#FF4A4A').should('have.css', 'width', '1366px');

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
          singleMetricGraphType: 'bar',
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
          singleMetricGraphType: 'bar',
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
          singleMetricGraphType: 'gauge',
          threshold: defaultThreshold
        }
      });

      cy.contains('34%').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-arc').should('have.attr', 'fill', '#88B922');

      cy.findAllByTestId('5-arc').should('have.length', 2);

      cy.findAllByTestId('5-arc').eq(0).trigger('mouseover');
      cy.contains(
        'Warning threshold: 65%. Value defined by the {{metric}} metric'
      ).should('be.visible');
      cy.contains(
        'Warning threshold: 70%. Value defined by the {{metric}} metric'
      ).should('be.visible');
      cy.findAllByTestId('5-arc').eq(0).trigger('mouseleave');

      cy.findAllByTestId('5-arc').eq(1).trigger('mouseover');
      cy.contains(
        'Critical threshold: 85%. Value defined by the {{metric}} metric'
      ).should('be.visible');
      cy.contains(
        'Critical threshold: 90%. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value with the default color when thresholds are disabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'gauge',
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
          singleMetricGraphType: 'gauge',
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
          singleMetricGraphType: 'gauge',
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
          singleMetricGraphType: 'gauge',
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
          singleMetricGraphType: 'gauge',
          threshold: defaultThreshold,
          valueFormat: 'raw'
        }
      });

      cy.contains('340032.4232 B').should('be.visible');
    });
  });
});
