import { Provider, createStore } from 'jotai';
import { Formik } from 'formik';
import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

import { Method, QueryProvider } from '@centreon/ui';

import { metricsEndpoint } from '../../../api/endpoints';
import { WidgetDataResource } from '../../../models';
import { labelSelectMetric } from '../../../../translatedLabels';
import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import MetricsOnly from './MetricsOnly';

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

const initializeComponent = ({ metrics, resources }): void => {
  const store = createStore();
  store.set(hasEditPermissionAtom, true);
  store.set(isEditingAtom, true);

  cy.fixture('Dashboards/Dashboard/serviceMetrics.json').then(
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
        <QueryProvider>
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
            <MetricsOnly label="" propertyName="metrics" />
          </Formik>
        </QueryProvider>
      </Provider>
    )
  });
};

describe('MetricsOnly', () => {
  beforeEach(() => {
    initializeComponent({ metrics: emptyMetrics, resources: defaultResources });
  });

  it('displays metrics with included hosts when resources are fulfilled', () => {
    cy.waitForRequest('@getServiceMetrics');

    cy.findByTestId(labelSelectMetric).click();
    cy.contains('rtmax (ms) / Includes 2 resources');
    cy.contains('pl (%) / Includes 2 resources');

    cy.makeSnapshot();
  });

  it('displays the selected metric when resources are fulfilled and a metric is selected', () => {
    cy.findByTestId(labelSelectMetric).click();
    cy.contains('rtmax (ms) / Includes 2 resources').click();

    cy.contains('rtmax (ms) / 2').should('be.visible');
    cy.contains('rtmax (ms) / 2').trigger('mouseover');
    cy.contains('rtmax (ms) / Includes 2 resources').should('be.visible');

    cy.makeSnapshot();
  });

  it('removes metrics item when the delete icon is clicked', () => {
    cy.findByTestId(labelSelectMetric).click();

    cy.findByText('rtmax (ms) / Includes 2 resources').click();
    cy.findByText('rtmax (ms) / 2').should('be.visible');

    cy.findByTestId('CancelIcon').click();
    cy.findByText('rtmax (ms) / 2').should('not.exist');

    cy.makeSnapshot();
  });
});
