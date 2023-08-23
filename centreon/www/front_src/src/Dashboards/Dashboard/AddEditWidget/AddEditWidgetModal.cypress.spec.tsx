/* eslint-disable import/no-unresolved */
import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
import widgetDataConfiguration from 'centreon-widgets/centreon-widget-data/moduleFederation.json';
import widgetDataProperties from 'centreon-widgets/centreon-widget-data/properties.json';
import widgetGenericTextConfiguration from 'centreon-widgets/centreon-widget-generictext/moduleFederation.json';
import widgetGenericTextProperties from 'centreon-widgets/centreon-widget-generictext/properties.json';
import widgetSingleDataConfiguration from 'centreon-widgets/centreon-widget-singledata/moduleFederation.json';
import widgetSingleDataProperties from 'centreon-widgets/centreon-widget-singledata/properties.json';

import { Method, TestQueryProvider } from '@centreon/ui';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../../federatedModules/atoms';
import {
  labelSave,
  labelDelete,
  labelShowDescription,
  labelMetrics,
  labelName,
  labelOpenLinksInNewTab,
  labelPleaseChooseAWidgetToActivatePreview,
  labelPleaseSelectAResource,
  labelResourceType,
  labelSelectAResource,
  labelSelectAWidgetType,
  labelServiceName,
  labelYouCanSelectUpToTwoMetricUnits,
  labelWidgetLibrary,
  labelExit,
  labelEditWidget,
  labelAddResource,
  labelAddMetric
} from '../translatedLabels';
import { dashboardAtom } from '../atoms';

import { widgetFormInitialDataAtom } from './atoms';
import { resourceTypeBaseEndpoints } from './WidgetProperties/Inputs/Resources/useResources';
import { WidgetResourceType } from './models';
import { metricsEndpoint } from './api/endpoints';

import { AddEditWidgetModal } from '.';

const initializeWidgets = (defaultStore?): ReturnType<typeof createStore> => {
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
    },
    {
      ...widgetGenericTextConfiguration,
      moduleFederationName: 'centreon-widget-genericText/src'
    },
    {
      ...widgetSingleDataConfiguration,
      moduleFederationName: 'centreon-widget-singledata/src'
    }
  ];

  const store = defaultStore || createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties,
    widgetDataProperties,
    widgetGenericTextProperties,
    widgetSingleDataProperties
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
  data: {},
  id: `centreon-widget-text_1`,
  moduleName: widgetTextConfiguration.moduleName,
  options: {
    description: {
      content:
        '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Description","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}',
      enabled: true
    },
    name: 'Widget name',
    openLinksInNewTab: false
  },
  panelConfiguration: {
    federatedComponents: ['./text'],
    path: '/widgets/text'
  }
};

const generateResources = (resourceLabel: string): object => ({
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
    beforeEach(() => {
      const jotaiStore = initializeWidgets();

      jotaiStore.set(widgetFormInitialDataAtom, initialFormDataAdd);

      cy.viewport('macbook-13');

      cy.mount({
        Component: (
          <TestQueryProvider>
            <Provider store={jotaiStore}>
              <AddEditWidgetModal />
            </Provider>
          </TestQueryProvider>
        )
      });
    });

    describe('Add widget', () => {
      beforeEach(() => {
        const jotaiStore = initializeWidgets();

        jotaiStore.set(widgetFormInitialDataAtom, initialFormDataAdd);

        cy.viewport('macbook-13');

        cy.mount({
          Component: (
            <TestQueryProvider>
              <Provider store={jotaiStore}>
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
        cy.findByLabelText(labelExit).should('be.visible');
        cy.findByLabelText(labelSave).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('enables the add button when a widget is selected and the properties are filled', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelSave).should('be.disabled');

        cy.findByLabelText(labelName).type('Generic input');
        cy.findByLabelText('Generic text').type('Text');
        cy.findByLabelText(labelShowDescription).should('be.checked');
        cy.findByLabelText(labelOpenLinksInNewTab).should('be.checked');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.matchImageSnapshot();
      });

      it('keeps the name when a widget is selected, properties are filled and the widget type is changed', () => {
        const widgetName = 'Widget name';

        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelName).type(widgetName);
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic text (example)').click();

        cy.findByLabelText(labelName).should('have.value', widgetName);
        cy.findByLabelText(labelSave).should('be.enabled');

        cy.matchImageSnapshot();
      });

      it('disables the description field when the display description checkbox is not checked', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText('RichTextEditor').should(
          'have.attr',
          'contenteditable',
          'true'
        );

        cy.findByLabelText(labelShowDescription).uncheck();

        cy.findByLabelText('RichTextEditor').should(
          'have.attr',
          'contenteditable',
          'false'
        );
      });
    });

    describe('Edit widget', () => {
      beforeEach(() => {
        const jotaiStore = initializeWidgets();

        jotaiStore.set(widgetFormInitialDataAtom, initialFormDataEdit);

        cy.viewport('macbook-13');

        cy.mount({
          Component: (
            <Provider store={jotaiStore}>
              <AddEditWidgetModal />
            </Provider>
          )
        });
      });

      it('displays the modal with pre-filled values', () => {
        cy.contains(labelEditWidget).should('be.visible');

        cy.findByLabelText(labelWidgetLibrary).should(
          'have.value',
          'Generic text (example)'
        );
        cy.findByLabelText(labelName).should('have.value', 'Widget name');
        cy.findByLabelText('RichTextEditor').contains('Description');
        cy.findByLabelText(labelSave).should('be.disabled');

        cy.matchImageSnapshot();
      });

      it('changes the widget type when another widget is selected', () => {
        const widgetName = 'Edited widget name';
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelName).clear().type(widgetName);
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelName).should('have.value', widgetName);
        cy.findByLabelText(labelSave).should('be.enabled');

        cy.matchImageSnapshot();
      });
    });

    it('displays the preview of the generic text widget when the generic text widget type is selected', () => {
      cy.findByLabelText(labelWidgetLibrary).click();
      cy.contains(/^Generic text$/).click();

      cy.findAllByLabelText('RichTextEditor').eq(2).type('Hello ');
      cy.findAllByLabelText('bold').eq(1).click();
      cy.findAllByLabelText('RichTextEditor').eq(2).type('World');
      cy.findAllByLabelText('bold').eq(1).click();
      cy.findAllByLabelText('RichTextEditor').eq(2).type(`
      
      
      Hello!
      https://centreon.com`);

      cy.findAllByLabelText('RichTextEditor').eq(0).contains('Hello World');
      cy.findAllByLabelText('RichTextEditor').eq(0).contains('Hello!');
      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .contains('https://centreon.com');

      cy.matchImageSnapshot();
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

        cy.findByLabelText(labelSave).should('be.disabled');

        cy.findByLabelText(labelAddResource).click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.contains(labelPleaseSelectAResource).should('be.visible');

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByLabelText(labelAddMetric).click();
        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.contains('Metrics (1 available)').should('be.visible');
        cy.contains(labelYouCanSelectUpToTwoMetricUnits).should('be.visible');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.matchImageSnapshot();
      });

      it('disables the Add button when metrics are removed from the dataset selection', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelName).type('Generic data');

        cy.findByLabelText(labelAddResource).click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByLabelText(labelAddMetric).click();
        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.findAllByLabelText(labelDelete).eq(1).click();

        cy.findByLabelText(labelSave).should('be.disabled');

        cy.matchImageSnapshot();
      });

      it('stores the data when a resource is selected, a metric is selected and the Add button is clicked', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelName).type('Generic data');

        cy.findByLabelText(labelAddResource).click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findAllByLabelText(labelAddMetric).click();
        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.findByLabelText(labelSave)
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
              1
            );
            assert.equal(dashboard.layout[0].data.metrics.length, 1);
            assert.equal(dashboard.layout[0].data.metrics[0].id, 1);
            assert.equal(dashboard.layout[0].data.metrics[0].metrics.length, 2);
          });
      });

      it('selects one metric when the widget allows only one metric', () => {
        cy.findByLabelText(labelWidgetLibrary).click();
        cy.contains('Generic data for single metric (example)').click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.contains(labelPleaseSelectAResource).should('be.visible');

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelServiceName).parent().children().eq(0).click();
        cy.contains('Centreon-server_Ping').click();

        cy.findByTestId(labelMetrics).click();
        cy.contains('pl (%)').click();

        cy.matchImageSnapshot();
      });
    });
  });
});
