import { Provider, createStore } from 'jotai';
import { Formik } from 'formik';
import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import { T, always, cond } from 'ramda';

import { Method, TestQueryProvider } from '@centreon/ui';

import { metricsEndpoint } from '../../../api/endpoints';
import { WidgetDataResource } from '../../../models';
import {
  labelAvailable,
  labelIsTheSelectedResource,
  labelMetrics,
  labelSelectMetric,
  labelYouHaveTooManyMetrics
} from '../../../../translatedLabels';
import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import {
  singleHostPerMetricAtom,
  singleMetricSelectionAtom
} from '../../../atoms';

import Metrics from './Metrics';

const emptyMetrics = [];

const defaultResources: Array<WidgetDataResource> = [
  {
    resourceType: 'host-group',
    resources: [
      {
        id: 1,
        name: 'Host group 1'
      },
      {
        id: 2,
        name: 'Host group 2'
      }
    ]
  }
];

const notFullfilledResources: Array<WidgetDataResource> = [
  {
    resourceType: 'host-group',
    resources: [
      {
        id: 1,
        name: 'Host group 1'
      },
      {
        id: 2,
        name: 'Host group 2'
      }
    ]
  },
  {
    resourceType: 'service',
    resources: []
  }
];

interface Props {
  hasTooManyMetrics?: boolean;
  metrics?;
  resources?;
  singleResourcePerMetric?: boolean;
}

const store = createStore();

const initializeComponent = ({
  metrics = emptyMetrics,
  resources = defaultResources,
  singleResourcePerMetric = false,
  hasTooManyMetrics = false
}: Props): void => {
  store.set(hasEditPermissionAtom, true);
  store.set(isEditingAtom, true);

  const fixtureName = cond([
    [() => singleResourcePerMetric, always('serviceMetric')],
    [() => hasTooManyMetrics, always('tooManyServiceMetrics')],
    [T, always('serviceMetrics')]
  ])();

  cy.fixture(`Dashboards/Dashboard/${fixtureName}.json`).then(
    (serviceMetrics) => {
      cy.interceptAPIRequest({
        alias: 'getServiceMetrics',
        method: Method.GET,
        path: `${metricsEndpoint}**`,
        response: serviceMetrics
      });
    }
  );

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestQueryProvider>
          <Formik
            initialValues={{
              data: {
                metrics,
                resources
              },
              moduleName: 'widget',
              options: {}
            }}
            onSubmit={cy.stub()}
          >
            <Metrics label="" propertyName="metrics" />
          </Formik>
        </TestQueryProvider>
      </Provider>
    )
  });
};

describe('Metrics', () => {
  it('displays metrics with included hosts when resources are fulfilled', () => {
    initializeComponent({});
    cy.waitForRequest('@getServiceMetrics');

    cy.findByTestId(labelSelectMetric).click();
    cy.contains('rtmax (ms)');
    cy.contains('pl (%)');

    cy.makeSnapshot();
  });

  describe('Single metric selection with single resource', () => {
    beforeEach(() => {
      initializeComponent({});
      store.set(singleHostPerMetricAtom, true);
      store.set(singleMetricSelectionAtom, true);
    });

    it('displays the retrieved metrics', () => {
      cy.waitForRequest('@getServiceMetrics');

      cy.findByTestId(labelSelectMetric).click();
      cy.contains('rtmax (ms)').should('be.visible');
      cy.contains('pl (%)').should('be.visible');

      cy.makeSnapshot();
    });

    it('selects a metric when metrics are retrieved and a metric name is clicked', () => {
      cy.findByTestId(labelSelectMetric).click();
      cy.contains('pl (%)').click();

      cy.findByTestId(labelSelectMetric).should('have.value', 'pl (%)');

      cy.makeSnapshot();
    });

    it('displays a warning when a metric with several resources is selected', () => {
      initializeComponent({
        metrics: emptyMetrics,
        resources: defaultResources,
        singleResourcePerMetric: false
      });
      cy.findByTestId(labelSelectMetric).click();
      cy.contains('pl (%)').click();

      cy.contains(`Centreon-1:Ping ${labelIsTheSelectedResource}`).should(
        'be.visible'
      );

      cy.makeSnapshot();
    });
  });

  describe('Metric header', () => {
    it('displays the number of metric available', () => {
      initializeComponent({});

      cy.contains(`${labelMetrics} (4 ${labelAvailable})`).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays a message when there are too many metrics to retrieve', () => {
      initializeComponent({ hasTooManyMetrics: true });

      cy.contains(`${labelMetrics} (1002 ${labelAvailable})`).should(
        'be.visible'
      );
      cy.contains(labelYouHaveTooManyMetrics).should('be.visible');
      cy.findByTestId(labelSelectMetric).should('be.disabled');

      cy.makeSnapshot();
    });

    it('disables the metrics selector when resources are not correctly fullfilled', () => {
      initializeComponent({ resources: notFullfilledResources });

      cy.findByTestId(labelSelectMetric).should('be.disabled');

      cy.makeSnapshot();
    });
  });

  describe.only('Single metric selection with several resources', () => {
    beforeEach(() => {
      store.set(singleHostPerMetricAtom, false);
      store.set(singleMetricSelectionAtom, true);
      initializeComponent({});
    });

    it('displays the metrics and their own resources when the selector is clicked and metrics option are clicked', () => {
      cy.findByTestId(labelSelectMetric).click();

      cy.contains('rtmax (ms)').should('be.visible');
      cy.findByTestId('rtmax').should('not.be.checked');
      cy.contains('pl (%)').should('be.visible');
      cy.findByTestId('pl').should('not.be.checked');

      cy.findByTestId('rtmax-summary').click();
      cy.get('[data-testid="rtmax-accordion"]')
        .contains('Centreon-1:Ping')
        .should('be.visible');
      cy.get('[data-testid="rtmax-accordion"]')
        .contains('Centreon-2:Ping')
        .should('be.visible');

      cy.findByTestId('pl-summary').click();
      cy.get('[data-testid="pl-accordion"]')
        .contains('Centreon-1:Ping')
        .should('be.visible');
      cy.get('[data-testid="pl-accordion"]')
        .contains('Centreon-2:Ping')
        .should('be.visible');

      cy.makeSnapshot();
    });

    it('selects a metrics when the options list is expanded and a metric is selected', () => {
      cy.findByTestId(labelSelectMetric).click();

      cy.findByTestId('rtmax').click();

      cy.findByTestId('rtmax').should('have.attr', 'data-checked', 'true');
      cy.findByTestId('pl').should('have.attr', 'data-checked', 'false');

      cy.contains('rtmax (ms)/2').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Multiple metrics selection with several resources', () => {
    beforeEach(() => {
      store.set(singleHostPerMetricAtom, false);
      store.set(singleMetricSelectionAtom, false);

      initializeComponent({});
    });
  });
});
