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
import widgetSingleMetricConfiguration from 'centreon-widgets/centreon-widget-singlemetric/moduleFederation.json';
import widgetSingleMetricProperties from 'centreon-widgets/centreon-widget-singlemetric/properties.json';
import widgetGraphConfiguration from 'centreon-widgets/centreon-widget-graph/moduleFederation.json';
import widgetGraphProperties from 'centreon-widgets/centreon-widget-graph/properties.json';
import widgetStatusGridConfiguration from 'centreon-widgets/centreon-widget-statusgrid/moduleFederation.json';
import widgetStatusGridProperties from 'centreon-widgets/centreon-widget-statusgrid/properties.json';
import widgetTopBottomConfiguration from 'centreon-widgets/centreon-widget-topbottom/moduleFederation.json';
import widgetTopBottomProperties from 'centreon-widgets/centreon-widget-topbottom/properties.json';

import { Method, TestQueryProvider } from '@centreon/ui';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../../../federatedModules/atoms';
import {
  labelSave,
  labelDelete,
  labelShowDescription,
  labelSelectMetric,
  labelTitle,
  labelOpenLinksInNewTab,
  labelPleaseChooseAWidgetToActivatePreview,
  labelResourceType,
  labelSelectAResource,
  labelSelectAWidgetType,
  labelYouCanSelectUpToTwoMetricUnits,
  labelWidgetType,
  labelCancel,
  labelEditWidget,
  labelRefineFilter,
  labelAddMetric
} from '../translatedLabels';
import { dashboardAtom, hasEditPermissionAtom, isEditingAtom } from '../atoms';

import { widgetFormInitialDataAtom } from './atoms';
import { resourceTypeBaseEndpoints } from './WidgetProperties/Inputs/Resources/useResources';
import { WidgetResourceType } from './models';
import { metricsEndpoint } from './api/endpoints';

import { AddEditWidgetModal } from '.';

const widgetsProperties = [
  widgetTextProperties,
  widgetInputProperties,
  widgetDataProperties,
  widgetGenericTextProperties,
  widgetSingleDataProperties,
  widgetStatusGridProperties,
  widgetSingleMetricProperties,
  widgetGraphProperties,
  widgetTopBottomProperties
];

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
    },
    {
      ...widgetSingleMetricConfiguration,
      moduleFederationName: 'centreon-widget-singlemetric/src'
    },
    {
      ...widgetStatusGridConfiguration,
      moduleFederationName: 'centreon-widget-statusgrid/src'
    },
    {
      ...widgetGraphConfiguration,
      moduleFederationName: 'centreon-widget-graph/src'
    },
    {
      ...widgetTopBottomConfiguration,
      moduleFederationName: 'centreon-widget-topbottom/src'
    }
  ];

  const store = defaultStore || createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, widgetsProperties);

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

const initialFormData = {
  data: {
    metrics: [
      {
        criticalHighThreshold: null,
        criticalLowThreshold: null,
        id: 0,
        metrics: [
          {
            id: 0,
            name: 'ping'
          }
        ],
        name: 'Service 1',
        unit: '%',
        warningHighThreshold: null,
        warningLowThreshold: null
      }
    ],
    resources: [
      {
        resourceType: 'host',
        resources: [
          {
            id: 0,
            name: 'Host 0'
          }
        ]
      }
    ]
  },
  id: `centreon-widget-data_1`,
  moduleName: widgetDataConfiguration.moduleName,
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
    federatedComponents: ['./data'],
    path: '/widgets/data'
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
      jotaiStore.set(hasEditPermissionAtom, true);
      jotaiStore.set(isEditingAtom, true);

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
        jotaiStore.set(hasEditPermissionAtom, true);
        jotaiStore.set(isEditingAtom, true);

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
        cy.findByLabelText(labelWidgetType).should('be.visible');
        cy.findByLabelText(labelCancel).should('be.visible');
        cy.findByLabelText(labelSave).should('be.visible');

        cy.makeSnapshot();
      });

      it('enables the add button when a widget is selected and the properties are filled', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelSave).should('be.disabled');

        cy.findByLabelText(labelTitle).type('Generic input');
        cy.findByLabelText('Generic text').type('Text');
        cy.findByLabelText(labelShowDescription).should('be.checked');
        cy.findByLabelText(labelOpenLinksInNewTab).should('be.checked');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.makeSnapshot();
      });

      it('keeps the name when a widget is selected, properties are filled and the widget type is changed', () => {
        const widgetName = 'Widget name';

        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelTitle).type(widgetName);
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic text (example)').click();

        cy.findByLabelText(labelTitle).should('have.value', widgetName);
        cy.findByLabelText(labelSave).should('be.enabled');

        cy.makeSnapshot();
      });

      it('does not disable the description field when the display description checkbox is not checked', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic input (example)').click();

        cy.findAllByLabelText('RichTextEditor')
          .eq(0)
          .should('have.attr', 'contenteditable', 'true');

        cy.findByLabelText(labelShowDescription).uncheck();

        cy.findAllByLabelText('RichTextEditor')
          .eq(0)
          .should('have.attr', 'contenteditable', 'true');
      });

      it('displays the title and the description in the preview when corresponding fields are edited', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelTitle).clear().type('Title');
        cy.findAllByLabelText('RichTextEditor').eq(0).type('Hello');

        cy.contains('Title').should('be.visible');
        cy.contains('Hello').should('be.visible');

        cy.makeSnapshot();
      });
    });

    describe('Edit widget', () => {
      beforeEach(() => {
        const jotaiStore = initializeWidgets();

        jotaiStore.set(widgetFormInitialDataAtom, initialFormDataEdit);
        jotaiStore.set(hasEditPermissionAtom, true);
        jotaiStore.set(isEditingAtom, true);

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

        cy.findByLabelText(labelWidgetType).should(
          'have.value',
          'Generic text (example)'
        );
        cy.findByLabelText(labelTitle).should('have.value', 'Widget name');
        cy.findAllByLabelText('RichTextEditor').eq(0).contains('Description');
        cy.contains('Widget name').should('be.visible');
        cy.findAllByLabelText('RichTextEditor').eq(1).contains('Description');
        cy.findByLabelText(labelSave).should('be.disabled');

        cy.makeSnapshot();
      });

      it('changes the widget type when another widget is selected', () => {
        const widgetName = 'Edited widget name';
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic input (example)').click();

        cy.findByLabelText(labelTitle).clear().type(widgetName);
        cy.findByLabelText('Generic text').type('Text');

        cy.findByLabelText(labelTitle).should('have.value', widgetName);
        cy.findByLabelText(labelSave).should('be.enabled');

        cy.makeSnapshot();
      });
    });

    it('displays the preview of the generic text widget when the generic text widget type is selected', () => {
      cy.findByLabelText(labelWidgetType).click();
      cy.contains(/^Generic text$/).click();

      cy.findAllByLabelText('RichTextEditor').eq(0).type('Hello ');
      cy.findByLabelText('format').click();
      cy.findByLabelText('bold').click();

      cy.findAllByLabelText('RichTextEditor').eq(0).type('World');
      cy.findByLabelText('format').click();
      cy.findByLabelText('bold').click();

      cy.findAllByLabelText('RichTextEditor').eq(0).type(`
      
      
      Hello!
      https://centreon.com`);

      cy.findAllByLabelText('RichTextEditor').eq(0).contains('Hello World');
      cy.findAllByLabelText('RichTextEditor').eq(0).contains('Hello!');
      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .contains('https://centreon.com');

      cy.makeSnapshot();
    });

    it('does not display the content of the generic text widget in the preview the show description switch is off', () => {
      cy.findByLabelText(labelWidgetType).click();
      cy.contains(/^Generic text$/).click();

      cy.findAllByLabelText('RichTextEditor').eq(1).type('Hello ');
      cy.findByLabelText('format').click();
      cy.findByLabelText('bold').click();

      cy.findAllByLabelText('RichTextEditor').eq(1).type('World');
      cy.findByLabelText('format').click();
      cy.findByLabelText('bold').click();

      cy.findAllByLabelText('RichTextEditor').eq(1).type(`
      
      
      Hello!
      https://centreon.com`);

      cy.findByLabelText(labelShowDescription).click();

      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .contains('Hello World')
        .should('not.exist');
      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .contains('Hello!')
        .should('not.exist');
      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .contains('https://centreon.com')
        .should('not.exist');

      cy.makeSnapshot();
    });

    it('displays widgets icon when widget type field is expanded', () => {
      cy.findByLabelText(labelWidgetType).click();

      widgetsProperties.forEach(({ title, description, icon }) => {
        cy.get(`[data-icon="${icon ? '' : 'default-'}${title}"]`).should(
          'exist'
        );
        cy.contains(title).should('exist');
        cy.contains(description).should('exist');
      });

      cy.makeSnapshot();
    });
  });

  describe('Disabled properties', () => {
    beforeEach(() => {
      const jotaiStore = initializeWidgets();

      jotaiStore.set(widgetFormInitialDataAtom, initialFormDataEdit);
      jotaiStore.set(hasEditPermissionAtom, true);
      jotaiStore.set(isEditingAtom, false);

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
            <Provider store={jotaiStore}>
              <AddEditWidgetModal />
            </Provider>
          </TestQueryProvider>
        )
      });
    });

    it('displays generic properties fields as disabled', () => {
      cy.findByLabelText(labelWidgetType).should('be.disabled');
      cy.findByLabelText(labelTitle).should('be.disabled');
      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .should('have.attr', 'contenteditable', 'false');
      cy.findByLabelText(labelShowDescription).should('be.disabled');
      cy.findByLabelText(labelOpenLinksInNewTab).should('be.disabled');
    });
  });

  describe('Data', () => {
    describe('Resources and metrics', () => {
      beforeEach(() => {
        initializeWidgets(store);

        store.set(widgetFormInitialDataAtom, initialFormDataAdd);
        store.set(hasEditPermissionAtom, true);
        store.set(isEditingAtom, true);

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

      it('does not suggest a selected resource type when adding new resource', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.findByText('Host 0').click();

        cy.findByLabelText(labelRefineFilter).click();

        cy.findAllByTestId(labelResourceType)
          .eq(1)
          .parent()
          .children()
          .eq(0)
          .click();

        cy.findAllByText(/^Host$/).should('have.length', 1);

        cy.makeSnapshot();
      });

      it('removes resource item when delete icon is clicked', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.findByText('Host 0').click();

        cy.findAllByText('Host 0').should('have.length', 1);
        cy.findByTestId('CancelIcon').click();
        cy.findAllByText('Host 0').should('have.length', 0);

        cy.makeSnapshot();
      });

      it('selects metrics when resources are selected', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelTitle).type('Generic data');

        cy.findByLabelText(labelRefineFilter).should('be.disabled');
        cy.findByLabelText(labelSave).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.findByLabelText(labelRefineFilter).should('be.disabled');

        cy.contains(/^Host 0$/).click();
        cy.findByLabelText(labelRefineFilter).should('not.be.disabled');
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.contains('Metrics (2 available)').should('be.visible');
        cy.contains(labelYouCanSelectUpToTwoMetricUnits).should('be.visible');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.makeSnapshot();
      });

      it('hides the delete button when there is only one resource ', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelTitle).type('Generic data');

        cy.findByLabelText(labelRefineFilter).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.contains('pl (%)').click();
        cy.contains('rtmax (ms)').click();

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.findAllByLabelText(labelDelete).eq(0).should('not.be.visible');

        cy.makeSnapshot();
      });

      it('stores the data when a resource is selected, a metric is selected and the Add button is clicked', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelTitle).type('Generic data');

        cy.findByLabelText(labelRefineFilter).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
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
            assert.equal(dashboard.layout[0].data.metrics.length, 2);
          });
      });

      it('selects one metric when the widget allows only one metric', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data for single metric (example)').click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');
        cy.findByLabelText(labelRefineFilter).should('be.disabled');

        cy.contains(/^Host 0$/).click();
        cy.findByLabelText(labelRefineFilter).should('be.enabled');
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.contains('pl (%)').click();

        cy.makeSnapshot();
      });
    });

    describe('With one service metrics', () => {
      beforeEach(() => {
        initializeWidgets(store);

        store.set(widgetFormInitialDataAtom, initialFormDataAdd);
        store.set(hasEditPermissionAtom, true);
        store.set(isEditingAtom, true);

        cy.viewport('macbook-13');

        cy.interceptAPIRequest({
          alias: 'getHosts',
          method: Method.GET,
          path: `**${resourceTypeBaseEndpoints[WidgetResourceType.host]}**`,
          response: generateResources('Host')
        });

        cy.fixture('Dashboards/Dashboard/serviceMetric.json').then(
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

      it('displays the metrics selection when the widget allows only one metric', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data for single metric (example)').click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');
        cy.findByLabelText(labelRefineFilter).should('be.disabled');

        cy.contains(/^Host 0$/).click();
        cy.findByLabelText(labelRefineFilter).should('be.enabled');
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.contains('pl (%)').click();
      });
    });
  });

  describe('Disabled data', () => {
    beforeEach(() => {
      const jotaiStore = initializeWidgets();

      jotaiStore.set(widgetFormInitialDataAtom, initialFormData);
      jotaiStore.set(hasEditPermissionAtom, true);
      jotaiStore.set(isEditingAtom, false);

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
            <Provider store={jotaiStore}>
              <AddEditWidgetModal />
            </Provider>
          </TestQueryProvider>
        )
      });
    });

    it('displays generic properties fields as disabled', () => {
      cy.findByTestId(labelResourceType).should('be.disabled');
      cy.findByLabelText(labelSelectAResource).should('be.disabled');
      cy.findByLabelText(labelSelectMetric).should('be.disabled');
      cy.contains(labelRefineFilter).should('not.exist');
      cy.contains(labelAddMetric).should('not.exist');
    });
  });
});
