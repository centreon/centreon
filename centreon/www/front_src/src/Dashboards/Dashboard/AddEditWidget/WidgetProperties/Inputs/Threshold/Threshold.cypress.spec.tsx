import { Formik } from 'formik';

import {
  labelCriticalThreshold,
  labelShowThresholds,
  labelThreshold,
  labelWarningThreshold
} from '../../../../translatedLabels';
import { ServiceMetric } from '../../../models';

import Threshold from './Threshold';

const emptyMetrics = [];
const selectedMetrics: Array<ServiceMetric> = [
  {
    id: 1,
    metrics: [
      {
        criticalThreshold: 100,
        id: 1,
        name: 'rta',
        unit: 'ms',
        warningThreshold: 35
      }
    ],
    name: 'Server_Ping'
  },
  {
    id: 2,
    metrics: [
      {
        criticalThreshold: 100,
        id: 2,
        name: 'idle',
        unit: '%',
        warningThreshold: 60
      },
      {
        criticalThreshold: 90,
        id: 3,
        name: 'user',
        unit: '%',
        warningThreshold: 80
      }
    ],
    name: 'Server_Cpu'
  }
];

const initializeComponent = ({ metrics, enabled = false }): void => {
  cy.mount({
    Component: (
      <Formik
        initialValues={{
          data: {
            metrics
          },
          moduleName: 'widget',
          options: {
            threshold: {
              criticalType: 'default',
              customCritical: null,
              customWarning: null,
              enabled,
              warningType: 'default'
            }
          }
        }}
        onSubmit={cy.stub()}
      >
        <Threshold label="" propertyName="threshold" />
      </Formik>
    )
  });
};

describe('Thresold', () => {
  it('does not display any default threshold values when no metrics are passed', () => {
    initializeComponent({ metrics: emptyMetrics });

    cy.contains(labelThreshold).should('be.visible');
    cy.contains(labelWarningThreshold).should('be.visible');
    cy.contains(labelCriticalThreshold).should('be.visible');
    cy.findByLabelText(labelShowThresholds).should('not.be.checked');
    cy.findAllByTestId('default').eq(0).children().eq(0).should('be.checked');
    cy.findAllByTestId('default').eq(1).children().eq(0).should('be.checked');
    cy.contains('Default ()').should('be.visible');
    cy.findAllByTestId(labelThreshold)
      .find('input')
      .each((element) => {
        cy.wrap(element).should('be.disabled');
      });

    cy.matchImageSnapshot();
  });

  it('displays the first metrics threshold values as default when some Resource metrics are passed', () => {
    initializeComponent({ metrics: selectedMetrics });

    cy.contains('Default (100)').should('be.visible');
    cy.contains('Default (35)').should('be.visible');

    cy.matchImageSnapshot();
  });

  it('enables the threshold fields when the Show Thresholds checkbox is checked and the Custom option is selected', () => {
    initializeComponent({ metrics: selectedMetrics });

    cy.findByLabelText(labelShowThresholds).click();
    cy.findAllByTestId('custom').eq(0).click();

    cy.findAllByTestId(labelThreshold).find('input').eq(0).should('be.enabled');
    cy.findAllByTestId(labelThreshold).find('input').eq(0).type('50');
    cy.findAllByTestId(labelThreshold)
      .find('input')
      .eq(1)
      .should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('does not reset the threshold value when the Show Thresholds checkbox is unchecked', () => {
    initializeComponent({ metrics: selectedMetrics });

    cy.findByLabelText(labelShowThresholds).click();
    cy.findAllByTestId('custom').eq(0).click();
    cy.findAllByTestId(labelThreshold).find('input').eq(0).type('50');
    cy.findByLabelText(labelShowThresholds).click();

    cy.findAllByTestId(labelThreshold)
      .find('input')
      .eq(0)
      .should('have.value', '50');

    cy.matchImageSnapshot();
  });

  it('unchecks the Show thresholds switch when different units are selected', () => {
    initializeComponent({ enabled: true, metrics: selectedMetrics });

    cy.findByLabelText(labelShowThresholds).should('not.be.checked');

    cy.matchImageSnapshot();
  });
});
