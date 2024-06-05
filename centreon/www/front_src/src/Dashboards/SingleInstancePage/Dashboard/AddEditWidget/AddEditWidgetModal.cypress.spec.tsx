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
import { federatedWidgetsAtom } from '@centreon/ui-context';

import { federatedWidgetsPropertiesAtom } from '../../../../federatedModules/atoms';
import {
  labelSave,
  labelDelete,
  labelShowDescription,
  labelSelectMetric,
  labelTitle,
  labelPleaseChooseAWidgetToActivatePreview,
  labelResourceType,
  labelSelectAResource,
  labelSelectAWidgetType,
  labelYouCanSelectUpToTwoMetricUnits,
  labelWidgetType,
  labelCancel,
  labelEditWidget,
  labelAddFilter,
  labelAddMetric,
  labelMetrics
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
    name: 'Widget name'
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
    name: 'Widget name'
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

    it('hides a property when an option value matches the condition', () => {
      cy.findByLabelText(labelWidgetType).click();
      cy.contains('Generic data (example)').click();

      cy.contains('Sort by').should('exist');

      cy.findByLabelText('Show thresholds').click();

      cy.contains('Sort by').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays general properties when a widget is selected', () => {
      cy.findByLabelText(labelWidgetType).click();
      cy.contains('Generic data (example)').click();

      cy.contains('General properties').click();

      cy.contains('Group name').should('exist');
      cy.contains('Select field').should('exist');

      cy.makeSnapshot();
    });

    it('displays sub inputs when the corresponding field has the correct value', () => {
      cy.findByLabelText(labelWidgetType).click();
      cy.contains('Generic data (example)').click();

      cy.contains('General properties').click();
      cy.contains('Button 3').click();

      cy.findByLabelText('Sub input 1').should('have.value', 'sample');
      cy.findByLabelText('Sub input 2').should('have.value', 'text');

      cy.contains('Button 4').click();

      cy.findAllByLabelText('Radio 1')
        .eq(0)
        .parent()
        .should('have.class', 'Mui-checked');

      cy.makeSnapshot();
    });

    it('keeps a sub-input value when a sub-input is displayed and its value is changed', () => {
      cy.findByLabelText(labelWidgetType).click();
      cy.contains('Generic data (example)').click();

      cy.contains('General properties').click();
      cy.findByLabelText('Button 3').click();

      cy.findAllByLabelText('Sub input 1').should('have.value', 'sample');
      cy.findAllByLabelText('Sub input 1').clear().type('updated value');

      cy.findByLabelText('Button 2').click();
      cy.findByLabelText('Button 3').click();

      cy.findAllByLabelText('Sub input 1').should(
        'have.value',
        'updated value'
      );

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
          query: {
            name: 'types',
            value: '["host"]'
          },
          response: generateResources('Host')
        });

        cy.interceptAPIRequest({
          alias: 'getMetaService',
          method: Method.GET,
          path: `**${resourceTypeBaseEndpoints[WidgetResourceType.metaService]}**`,
          query: {
            name: 'types',
            value: '["metaservice"]'
          },
          response: generateResources('Meta service')
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

        cy.findByLabelText(labelAddFilter).click();

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

        cy.findByLabelText(labelAddFilter).should('be.disabled');
        cy.findByLabelText(labelSave).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.contains(/^Host 0$/).click();
        cy.findByLabelText(labelAddFilter).should('not.be.disabled');
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('pl').click();
        cy.findByTestId('rtmax').click();
        cy.findByTestId(labelSelectMetric).click();

        cy.contains('Metrics (4 available)').should('be.visible');
        cy.contains(labelYouCanSelectUpToTwoMetricUnits).should('be.visible');

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.makeSnapshot();
      });

      it('hides the delete button when there is only one resource ', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelTitle).type('Generic data');

        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('pl').click();
        cy.findByTestId('rtmax').click();

        cy.findByLabelText(labelSave).should('be.enabled');

        cy.findAllByLabelText(labelDelete).eq(0).should('not.be.visible');

        cy.makeSnapshot();
      });

      it('stores the data with an excluded resource when a resource is selected, a metric is selected, a resource is unchecked and the Add button is clicked', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('pl').click();
        cy.findByTestId('pl-summary').click();
        cy.findByTestId('pl_Centreon-1:Ping').click();
        cy.findByTestId(labelSelectMetric).click();

        cy.findByLabelText(labelSave)
          .click()
          .then(() => {
            const dashboard = store.get(dashboardAtom);
            expect(dashboard.layout[0].data.metrics[0]).to.deep.equal({
              criticalHighThreshold: 1000,
              criticalLowThreshold: null,
              excludedMetrics: [2],
              id: 2,
              includeAllMetrics: true,
              name: 'pl',
              unit: '%',
              warningHighThreshold: null,
              warningLowThreshold: null
            });
          });
      });

      it('stores the data when a resource is selected, a metric is selected and the Add button is clicked', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelTitle).type('Generic data');

        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 0$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('rtmax').click();
        cy.findByTestId(labelSelectMetric).click();

        cy.findByLabelText(labelSave)
          .click()
          .then(() => {
            const dashboard = store.get(dashboardAtom);

            assert.equal(dashboard.layout.length, 2);
            assert.equal(dashboard.layout[1].data.resources.length, 1);
            assert.equal(
              dashboard.layout[1].data.resources[0].resourceType,
              'host'
            );
            assert.equal(
              dashboard.layout[1].data.resources[0].resources.length,
              1
            );
            assert.equal(dashboard.layout[1].data.metrics.length, 1);
          });
      });

      it('selects one metric when the widget allows only one metric', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data for single metric (example)').click();

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');
        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.contains(/^Host 0$/).click();
        cy.findByLabelText(labelAddFilter).should('be.enabled');
        cy.waitForRequest('@getServiceMetrics');

        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('pl').click();

        cy.makeSnapshot();
      });

      it('removes the selected resource from the metric selector when the corresponding resource is removed from the resource selector', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data (example)').click();

        cy.findByLabelText(labelTitle).type('Generic data');

        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.findByTestId(labelResourceType).parent().children().eq(0).click();
        cy.contains(/^Host$/).click();

        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getHosts');

        cy.contains(/^Host 1$/).click();
        cy.findByTestId(labelSelectAResource).click();
        cy.contains(/^Host 2$/).click();
        cy.waitForRequest('@getServiceMetrics');

        cy.fixture('Dashboards/Dashboard/serviceMetric.json').then(
          (serviceMetric) => {
            cy.interceptAPIRequest({
              alias: 'getServiceMetric',
              method: Method.GET,
              path: `${metricsEndpoint}**`,
              response: serviceMetric
            });
          }
        );

        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('pl').click();
        cy.findByTestId(labelSelectMetric).click();

        cy.contains('pl (%)/2').should('be.visible');

        cy.contains(/^Host 2$/)
          .parent()
          .findByTestId('CancelIcon')
          .click();

        cy.waitForRequest('@getServiceMetric');
        cy.contains('pl (%)/1').should('be.visible');
        cy.findByTestId(labelSelectMetric).click();
        cy.findByTestId('pl-summary').click();
        cy.findByTestId('pl').should('have.attr', 'data-checked', 'true');
        cy.findByTestId('pl_Centreon-1:Ping').should(
          'have.attr',
          'data-checked',
          'true'
        );
      });

      it('hides metrics field when the Meta service resource type is selected and the Meta service is chosen', () => {
        cy.findByLabelText(labelWidgetType).click();
        cy.contains('Generic data for single metric (example)').click();

        cy.contains(labelMetrics).should('be.visible');

        cy.findByTestId(labelResourceType).parent().click();
        cy.contains(/^Meta service$/).click();
        cy.findByTestId(labelSelectAResource).click();
        cy.waitForRequest('@getMetaService');
        cy.contains('Meta service 0').click();

        cy.contains(labelMetrics).should('not.exist');

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
        cy.findByLabelText(labelAddFilter).should('be.disabled');

        cy.contains(/^Host 0$/).click();
        cy.findByLabelText(labelAddFilter).should('be.enabled');
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
      cy.contains(labelAddFilter).should('not.exist');
      cy.contains(labelAddMetric).should('not.exist');
    });
  });

  describe('No widgets', () => {
    beforeEach(() => {
      const jotaiStore = createStore();
      jotaiStore.set(federatedWidgetsAtom, []);
      jotaiStore.set(federatedWidgetsPropertiesAtom, null);
      jotaiStore.set(widgetFormInitialDataAtom, initialFormDataAdd);
      jotaiStore.set(hasEditPermissionAtom, true);
      jotaiStore.set(isEditingAtom, true);

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

    it('does not display widgets when any widgets are registered', () => {
      cy.findByTestId(labelWidgetType).click();

      cy.contains('No options').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Unrecognized widget property', () => {
    beforeEach(() => {
      const jotaiStore = initializeWidgets();
      jotaiStore.set(federatedWidgetsPropertiesAtom, [
        {
          description: 'This is the description of the data widget',
          moduleName: 'centreon-widget-data',
          options: {
            threshold: {
              defaultValue: '',
              label: 'threshold',
              type: 'unknown'
            }
          },
          title: 'Generic data (example)'
        }
      ]);
      jotaiStore.set(widgetFormInitialDataAtom, initialFormDataAdd);
      jotaiStore.set(hasEditPermissionAtom, true);
      jotaiStore.set(isEditingAtom, true);

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

    it('does not display the widget property when it is not recognized', () => {
      cy.findByTestId(labelWidgetType).click();
      cy.contains('Generic data').click();

      cy.findByTestId('unknown widget property').should('exist');

      cy.makeSnapshot();
    });
  });
});
