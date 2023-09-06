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
  options = {
    singleMetricGraphType: 'text',
    threshold: defaultThreshold
  }
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
        <Widget panelData={data} panelOptions={options} store={store} />
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
          threshold: defaultThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'color', 'rgb(136, 185, 34)');
      cy.contains('Warning: 65 - 70 %').should(
        'have.css',
        'color',
        'rgb(253, 155, 39)'
      );
      cy.contains('Critical: 85 - 90 %').should(
        'have.css',
        'color',
        'rgb(255, 74, 74)'
      );

      cy.makeSnapshot();
    });

    it('displays the metric value as success when thresholds are disabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'text',
          threshold: disabledThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'color', 'rgb(136, 185, 34)');
      cy.contains('Warning: 70 %').should('not.exist');
      cy.contains('Critical: 90 %').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the metric value as warning when the warning threshold is customized', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'text',
          threshold: warningThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'color', 'rgb(253, 155, 39)');

      cy.makeSnapshot();
    });

    it('displays the metric value as critical when the critical threshold is customized', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'text',
          threshold: criticalThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'color', 'rgb(255, 74, 74)');
      cy.contains('Warning: 10 %').should('be.visible');
      cy.contains('Critical: 20 %').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Single bar', () => {
    it('displays the metric value as success and thresholds when thresholds are enabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'bar',
          threshold: defaultThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-bar-#88B922').should('be.visible');

      cy.findByTestId('warning-line-65-tooltip').trigger('mouseover');
      cy.contains(
        'Warning threshold: 65 %. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('warning-line-70-tooltip').trigger('mouseover');
      cy.contains(
        'Warning threshold: 70 %. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('critical-line-85-tooltip').trigger('mouseover');
      cy.contains(
        'Critical threshold: 85 %. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.findByTestId('critical-line-90-tooltip').trigger('mouseover');
      cy.contains(
        'Critical threshold: 90 %. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value as success when thresholds are disabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'bar',
          threshold: disabledThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-bar-#88B922').should('be.visible');

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

      cy.contains('34 %').should('have.css', 'fill', 'rgb(253, 155, 39)');
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

      cy.contains('34 %').should('have.css', 'fill', 'rgb(255, 74, 74)');
      cy.findByTestId('34-bar-#FF4A4A').should('be.visible');

      cy.findByTestId('warning-line-10-tooltip').trigger('mouseover');
      cy.contains('Warning threshold: 10 %. Value customized').should(
        'be.visible'
      );

      cy.findByTestId('critical-line-20-tooltip').trigger('mouseover');
      cy.contains('Critical threshold: 20 %. Value customized').should(
        'be.visible'
      );

      cy.makeSnapshot();
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

      cy.contains('34 %').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-arc').should('have.attr', 'fill', '#88B922');

      cy.findAllByTestId('5-arc').should('have.length', 2);

      cy.findAllByTestId('5-arc').eq(0).trigger('mouseover');
      cy.contains(
        'Warning threshold: 65 %. Value defined by the {{metric}} metric'
      ).should('be.visible');
      cy.contains(
        'Warning threshold: 70 %. Value defined by the {{metric}} metric'
      ).should('be.visible');
      cy.findAllByTestId('5-arc').eq(0).trigger('mouseleave');

      cy.findAllByTestId('5-arc').eq(1).trigger('mouseover');
      cy.contains(
        'Critical threshold: 85 %. Value defined by the {{metric}} metric'
      ).should('be.visible');
      cy.contains(
        'Critical threshold: 90 %. Value defined by the {{metric}} metric'
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the metric value as success when thresholds are disabled', () => {
      initializeComponent({
        options: {
          singleMetricGraphType: 'gauge',
          threshold: disabledThreshold
        }
      });

      cy.contains('34 %').should('have.css', 'fill', 'rgb(136, 185, 34)');
      cy.findByTestId('34-arc').should('have.attr', 'fill', '#88B922');

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

      cy.contains('34 %').should('have.css', 'fill', 'rgb(253, 155, 39)');
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

      cy.contains('34 %').should('have.css', 'fill', 'rgb(255, 74, 74)');
      cy.findAllByTestId('34-arc').eq(1).should('have.attr', 'fill', '#FF4A4A');

      cy.findByTestId('24-arc').should('have.attr', 'fill', '#FD9B27');
      cy.findByTestId('14-arc').should('have.attr', 'fill', '#FF4A4A');

      cy.makeSnapshot();
    });
  });
});
