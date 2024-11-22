import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import {
  labelCriticalThreshold,
  labelShowThresholds,
  labelThresholds,
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
        criticalHighThreshold: 100,
        criticalLowThreshold: 50,
        id: 1,
        name: 'rta',
        unit: 'ms',
        warningHighThreshold: 35,
        warningLowThreshold: 10
      }
    ],
    name: 'Server_Ping'
  },
  {
    id: 2,
    metrics: [
      {
        criticalHighThreshold: 100,
        criticalLowThreshold: null,
        id: 2,
        name: 'idle',
        unit: '%',
        warningHighThreshold: 60,
        warningLowThreshold: null
      },
      {
        criticalHighThreshold: 90,
        criticalLowThreshold: null,
        id: 3,
        name: 'user',
        unit: '%',
        warningHighThreshold: 80,
        warningLowThreshold: null
      }
    ],
    name: 'Server_Cpu'
  }
];

const initializeComponent = ({
  metrics,
  enabled = false,
  canEdit = true
}): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.mount({
    Component: (
      <Provider store={store}>
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
      </Provider>
    )
  });
};

describe('Threshold', () => {
  it('does not display any default threshold values when no metrics are passed', () => {
    initializeComponent({ enabled: true, metrics: emptyMetrics });

    cy.contains(labelThresholds).should('be.visible');
    cy.contains(labelWarningThreshold).should('be.visible');
    cy.contains(labelCriticalThreshold).should('be.visible');
    cy.findByLabelText(labelShowThresholds).should('be.checked');
    cy.findAllByTestId('default').eq(0).children().eq(0).should('be.checked');
    cy.findAllByTestId('default').eq(1).children().eq(0).should('be.checked');
    cy.contains('Default (none)').should('be.visible');
    cy.findByTestId(labelThresholds).should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the first metrics threshold values as default when some Resource metrics are passed', () => {
    initializeComponent({ enabled: true, metrics: selectedMetrics });

    cy.contains('Default (10 ms - 35 ms)').should('be.visible');
    cy.contains('Default (50 ms - 100 ms)').should('be.visible');

    cy.makeSnapshot();
  });

  it('enables the threshold fields when the Show Thresholds checkbox is checked and the Custom option is selected', () => {
    initializeComponent({ metrics: selectedMetrics });

    cy.findByLabelText(labelShowThresholds).click();
    cy.findAllByTestId('custom').eq(0).click();

    cy.findAllByTestId(labelThresholds)
      .find('input')
      .eq(0)
      .should('be.enabled');
    cy.findAllByTestId(labelThresholds).find('input').eq(0).type('50');
    cy.contains('50 ms').should('be.visible');
    cy.findAllByTestId(labelThresholds).find('input').eq(1).should('not.exist');

    cy.makeSnapshot();
  });

  it('unchecks the Show thresholds switch when different units are selected', () => {
    initializeComponent({ enabled: true, metrics: selectedMetrics });

    cy.findByLabelText(labelShowThresholds).should('not.be.checked');

    cy.makeSnapshot();
  });
});

describe('Disabled threshold', () => {
  beforeEach(() => {
    initializeComponent({
      canEdit: false,
      enabled: true,
      metrics: selectedMetrics
    });
  });

  it('displays fields as disabled', () => {
    cy.findByLabelText(labelShowThresholds).should('be.disabled');
    cy.findAllByTestId('default').eq(0).children().eq(0).should('be.disabled');
    cy.findAllByTestId('default').eq(1).children().eq(0).should('be.disabled');
    cy.findAllByTestId('custom').eq(0).children().eq(0).should('be.disabled');
    cy.findAllByTestId('custom').eq(1).children().eq(0).should('be.disabled');
    cy.findByTestId(labelThresholds).should('not.exist');

    cy.makeSnapshot();
  });
});
