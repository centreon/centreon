/* eslint-disable import/no-unresolved */
import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
import widgetDataConfiguration from 'centreon-widgets/centreon-widget-data/moduleFederation.json';
import widgetDataProperties from 'centreon-widgets/centreon-widget-data/properties.json';

import { Method, TestQueryProvider } from '@centreon/ui';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../../federatedModules/atoms';
import {
  labelAdd,
  labelDelete,
  labelDescription,
  labelEdit,
  labelMetrics,
  labelName,
  labelPleaseChooseAWidgetToActivatePreview,
  labelPleaseSelectAResource,
  labelResourceType,
  labelSelectAResource,
  labelSelectAWidgetType,
  labelServiceName,
  labelTheLimiteOf2UnitsHasBeenReached,
  labelWidgetLibrary
} from '../translatedLabels';
import { labelCancel } from '../../translatedLabels';
import { dashboardAtom } from '../atoms';

import { widgetFormInitialDataAtom } from './atoms';
import { resourceTypeBaseEndpoints } from './WidgetProperties/Inputs/useResources';
import { WidgetResourceType } from './models';
import { metricsEndpoint } from './api/endpoints';

import { AddEditWidgetModal } from '.';

const initializeWidgets = (defaultStore): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    },
    {
      ...widgetDataConfiguration,
      moduleFederationName: 'centreon-widget-data/src'
    }
  ];

  const store = defaultStore || createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties,
    widgetDataProperties
  ]);

  return store;
};

const initialFormDataAdd = {
  data: {},
  id: null,
  moduleName: null,
  options: {},
  panelConfiguration: null
};

const initialFormDataEdit = {
  id: `centreon-widget-text_1`,
  moduleName: widgetTextConfiguration.moduleName,
  options: {
    description: 'Description',
    name: 'Widget name'
  },
  panelConfiguration: {
    federatedComponents: ['./text'],
    path: '/widgets/text'
  }
};

const generateResources = (resourceLabel: string) => ({
  meta: {
    limit: 10,
    page: 1,
    total: 10
  },
  result: new Array(10).fill(null).map((_, index) => ({
    id: index,
    name: `${resourceLabel} ${index}`
  }))
});

const store = createStore();

describe('AddEditWidgetModal', () => {
  describe('Properties', () => {
    describe('Add widget', () => {
      beforeEach(() => {
        const store = initializeWidgets();

        store.set(widgetFormInitialDataAtom, initialFormDataAdd);

        cy.viewport('macbook-13');

        cy.mount({
          Component: (
            <TestQueryProvider>
              <Provider store={store}>
                <AddEditWidgetModal />
              </Provider>
            </TestQueryProvider>
          )
        });
      });

      it('displays the modal', () => {
        cy.contains(labelSelectAWidgetType).should('be.visible');
        cy.contains(labelPleaseChooseAWidgetToActivatePreview).should(
          'be.visible'
        );
        cy.findByLabelText(labelWidgetLibrary).should('be.visible');
        cy.findByLabelText(labelCancel).should('be.visible');
        cy.findByLabelText(labelAdd).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('enables the add button when a widget is selected and the properties are filled', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelAdd).should('be.disabled');

        cy.findByLabelText(labelName).type('Generic input');
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelAdd).should('be.enabled');

        cy.matchImageSnapshot();
      });

      it('keeps the name when a widget is selected, properties are filled and the widget type is changed', () => {
        const widgetName = 'Widget name';

        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelName).type(widgetName);
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelAdd).should('be.enabled');

        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic text (example)').click();

        cy.findByLabelText(labelName).should('have.value', widgetName);
        cy.findByLabelText(labelAdd).should('be.enabled');

        cy.matchImageSnapshot();
      });
    });

    describe('Edit widget', () => {
      beforeEach(() => {
        const store = initializeWidgets();

        store.set(widgetFormInitialDataAtom, initialFormDataEdit);

        cy.viewport('macbook-13');

        cy.mount({
          Component: (
            <Provider store={store}>
              <AddEditWidgetModal />
            </Provider>
          )
        });
      });

      it('displays the modal with pre-filled values', () => {
        cy.contains(labelSelectAWidgetType).should('be.visible');

        cy.findByLabelText(labelWidgetLibrary).should(
          'have.value',
          'Generic text (example)'
        );
        cy.findByLabelText(labelName).should('have.value', 'Widget name');
        cy.findByLabelText(labelDescription).should(
          'have.value',
          'Description'
        );
        cy.findByLabelText(labelEdit).should('be.disabled');

        cy.matchImageSnapshot();
      });

      it('changes the widget type when another widget is selected', () => {
        const widgetName = 'Edited widget name';
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelName).clear().type(widgetName);
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelName).should('have.value', widgetName);
        cy.findByLabelText(labelEdit).should('be.enabled');

        cy.matchImageSnapshot();
      });
    });
  });

  describe('Data', () => {
    describe('Resources and metrics', () => {
      beforeEach(() => {
        initializeWidgets(store);

        store.set(widgetFormInitialDataAtom, initialFormDataAdd);

        cy.viewport('macbook-13');

        cy.interceptAPIRequest({
          alias: 'getHosts',
          method: Method.GET,
          path: `**${resourceTypeBaseEndpoints[WidgetResourceType.host]}**`,
          response: generateResources('Host')
        });

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

        cy.mount({
          Component: (
            <TestQueryProvider>
              <Provider store={store}>
                <AddEditWidgetModal />
              </Provider>
            </TestQueryProvider>
          )
        });
      });

      it('selects metrics when resources are selected', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelName).type('Generic data');

        cy.findAllByLabelText(labelAdd).eq(1).should('be.disabled');

        cy.findAllByLabelText(labelAdd).eq(0).click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.contains(labelPleaseSelectAResource).should('be.visible');

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');
        cy.contains(/^Host 1$/).click();
        cy.waitForRequest('@getServiceMetrics').then(() => {
          cy.getRequestCalls('@getServiceMetrics').then((calls) => {
            expect(calls).to.have.length(2);
          });
        });

        cy.findAllByLabelText(labelAdd).eq(1).click();
        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.contains('Metrics (1 Metric)').should('be.visible');
        cy.contains(labelTheLimiteOf2UnitsHasBeenReached).should('be.visible');

        cy.findAllByLabelText(labelAdd).eq(2).should('be.enabled');

        cy.matchImageSnapshot();
      });

      it('disables the Add button when metrics are removed from the dataset selection', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelName).type('Generic data');

        cy.findAllByLabelText(labelAdd).eq(0).click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findAllByLabelText(labelAdd).eq(1).click();
        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.findAllByLabelText(labelAdd).eq(2).should('be.enabled');

        cy.findAllByLabelText(labelDelete).eq(1).click();

        cy.findAllByLabelText(labelAdd).eq(2).should('be.disabled');

        cy.matchImageSnapshot();
      });

      it('stores the data when a resource is selected, a metric is selected and the Add button is clicked', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelName).type('Generic data');

        cy.findAllByLabelText(labelAdd).eq(0).click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findAllByLabelText(labelAdd).eq(1).click();
        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.findAllByLabelText(labelAdd)
          .eq(2)
          .click()
          .then(() => {
            const dashboard = store.get(dashboardAtom);

            assert.equal(dashboard.layout.length, 1);
            assert.equal(dashboard.layout[0].data.resources.length, 1);
            assert.equal(
              dashboard.layout[0].data.resources[0].resourceType,
              'host'
            );
            assert.equal(
              dashboard.layout[0].data.resources[0].resources.length,
              2
            );
            assert.equal(dashboard.layout[0].data.metrics.length, 1);
            assert.equal(dashboard.layout[0].data.metrics[0].id, 1);
            assert.equal(dashboard.layout[0].data.metrics[0].metrics.length, 2);
          });
      });
    });
  });
});
