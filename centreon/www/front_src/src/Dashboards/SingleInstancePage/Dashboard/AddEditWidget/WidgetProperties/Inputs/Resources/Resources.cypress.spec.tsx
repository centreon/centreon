/* eslint-disable import/no-unresolved */

import widgetDataProperties from 'centreon-widgets/centreon-widget-data/properties.json';
import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';
import { difference, equals, pluck, reject } from 'ramda';

import { Method, TestQueryProvider } from '@centreon/ui';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import {
  labelAddFilter,
  labelHostCategory,
  labelHostGroup,
  labelResourceType,
  labelSelectAResource,
  labelServiceCategory,
  labelServiceGroup
} from '../../../../translatedLabels';
import { widgetPropertiesAtom } from '../../../atoms';
import { WidgetResourceType } from '../../../models';

import Resources from './Resources';
import { resourceTypeBaseEndpoints, resourceTypeOptions } from './useResources';

import type { FederatedWidgetProperties } from 'www/front_src/src/federatedModules/models';

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

interface InitializeProps {
  emptyData?: boolean;
  hasEditPermission?: boolean;
  isEditing?: boolean;
  properties?: FederatedWidgetProperties;
  restrictedResourceTypes?: Array<string>;
  singleHostPerMetric?: boolean;
  singleMetricSelection?: boolean;
  singleResourceType?: boolean;
}

const initialize = ({
  isEditing = true,
  hasEditPermission = true,
  singleResourceType = false,
  restrictedResourceTypes = [],
  singleHostPerMetric = false,
  singleMetricSelection = false,
  emptyData = false,
  properties = widgetDataProperties
}: InitializeProps): void => {
  const store = createStore();
  store.set(widgetPropertiesAtom, {
    ...properties,
    singleHostPerMetric,
    singleMetricSelection
  });
  store.set(isEditingAtom, isEditing);
  store.set(hasEditPermissionAtom, hasEditPermission);

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
    alias: 'getServices',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.service]}**`,
    response: generateResources('Service')
  });

  cy.interceptAPIRequest({
    alias: 'getHostGroup',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.hostGroup]}**`,
    response: generateResources('Host Group')
  });

  cy.interceptAPIRequest({
    alias: 'getServiceGroup',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.serviceGroup]}**`,
    response: generateResources('Service Group')
  });

  cy.interceptAPIRequest({
    alias: 'getHostCategory',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.hostCategory]}**`,
    response: generateResources('Host Category')
  });

  cy.interceptAPIRequest({
    alias: 'getServiceCategory',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.serviceCategory]}**`,
    response: generateResources('Service Category')
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Formik
            initialValues={{
              data: emptyData
                ? {}
                : {
                    resources: []
                  },
              moduleName: 'widget',
              options: {}
            }}
            onSubmit={cy.stub()}
          >
            <Resources
              label=""
              propertyName="resources"
              restrictedResourceTypes={restrictedResourceTypes}
              singleResourceType={singleResourceType}
              type=""
            />
          </Formik>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Resources', () => {
  it('displays host and service type when the corresponding atom is set to true', () => {
    initialize({ singleHostPerMetric: true, singleMetricSelection: true });

    cy.findAllByTestId(labelResourceType).eq(0).should('have.value', 'host');
    cy.findAllByTestId(labelResourceType).eq(1).should('have.value', 'service');
    cy.findAllByTestId(labelSelectAResource).eq(1).should('be.disabled');

    cy.findAllByTestId(labelSelectAResource).eq(0).click();
    cy.waitForRequest('@getHosts');
    cy.contains('Host 0').click();

    cy.findAllByTestId(labelSelectAResource).eq(1).click();
    cy.waitForRequest('@getServices').then(({ request }) => {
      expect(request.url.href).contain(
        'page=1&limit=30&search=%7B%22%24and%22%3A%5B%7B%22%24or%22%3A%5B%7B%22host.name%22%3A%7B%22%24in%22%3A%5B%22Host%200%22%5D%7D%7D%5D%7D%5D%7D'
      );
    });
    cy.contains('Service 0').click();

    cy.findAllByTestId(labelSelectAResource)
      .eq(0)
      .should('have.value', 'Host 0');
    cy.findAllByTestId(labelSelectAResource)
      .eq(1)
      .should('have.value', 'Service 0');

    cy.makeSnapshot();
  });

  it('adds a new filter line when the first resource line is fullfilled add the button is clicked', () => {
    initialize({});

    cy.contains(labelAddFilter).should('be.disabled');

    cy.findByTestId(labelResourceType).parent().click();
    cy.contains(/^Host$/).click();
    cy.findByTestId(labelSelectAResource).click();
    cy.waitForRequest('@getHosts');
    cy.contains('Host 0').click();

    cy.contains(labelAddFilter).click();

    cy.findAllByTestId(labelResourceType).should('have.length', 2);

    cy.makeSnapshot();
  });

  it('does not display the Add filter button when the corresponding property is set to true', () => {
    initialize({ singleResourceType: true });

    cy.contains(labelAddFilter).should('not.exist');

    cy.makeSnapshot();
  });

  it('displays only the restricted resource types when the propety is defined', () => {
    initialize({
      restrictedResourceTypes: ['host-group', 'host', 'service-category']
    });

    cy.findByTestId(labelResourceType).parent().click();

    cy.contains(/^Host Group$/).should('be.visible');
    cy.contains(/^Host$/).should('be.visible');
    cy.contains(/^Service Category$/).should('be.visible');
    cy.contains(/^Service$/).should('not.exist');
    cy.contains(/^Host Category$/).should('not.exist');
    cy.contains(/^Service Group$/).should('not.exist');

    cy.makeSnapshot();
  });

  it('deletes a resource when the corresponding icon is clicked', () => {
    initialize({});

    cy.findByTestId(labelResourceType).parent().click();
    cy.contains(/^Host$/).click();
    cy.findByTestId(labelSelectAResource).click();
    cy.waitForRequest('@getHosts');
    cy.contains('Host 0').click();
    cy.findByTestId('CancelIcon').click();

    cy.contains('Host 0').should('not.exist');

    cy.makeSnapshot();
  });

  it('selects a resource type and a resource when the data value does not exist', () => {
    initialize({ emptyData: true });

    cy.contains(labelAddFilter).click();
    cy.findByTestId(labelResourceType).parent().click();
    cy.contains(/^Host$/).click();
    cy.findByTestId(labelSelectAResource).click();
    cy.waitForRequest('@getHosts');
    cy.contains('Host 0').click();

    cy.findByTestId(labelResourceType).should('have.value', 'host');
    cy.contains('Host 0').should('be.visible');

    cy.findByTestId('CancelIcon').click();

    cy.contains('Host 0').should('not.exist');
  });
});

describe('Resources disabled', () => {
  it('displays fields as disabled when the edition mode is not activated', () => {
    initialize({ isEditing: false });

    cy.findByTestId(labelResourceType).should('be.disabled');
    cy.findByTestId(labelSelectAResource).should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays fields as disabled when rights are not sufficient', () => {
    initialize({ hasEditPermission: false });

    cy.findByTestId(labelResourceType).should('be.disabled');
    cy.findByTestId(labelSelectAResource).should('be.disabled');

    cy.makeSnapshot();
  });
});

const resourceTypesNames = pluck('name', resourceTypeOptions);

describe('Resources tree', () => {
  beforeEach(() => initialize({}));

  it('ensures that all resource types are available in the first line item of the dataset selection', () => {
    cy.findByTestId(labelResourceType).parent().click();
    resourceTypesNames.forEach((resourceType) => {
      cy.contains(resourceType).should('be.visible');
    });

    cy.makeSnapshot();
  });

  it("confirms that the 'Add Filter' button is disabled when the 'Service' type is selected", () => {
    cy.findByTestId(labelResourceType).parent().click();

    cy.contains('Service').click();

    cy.findByTestId(labelAddFilter).should('be.disabled');

    cy.makeSnapshot();
  });

  reject(
    ({ id }) => equals(id, WidgetResourceType.service),
    resourceTypeOptions
  ).forEach(({ availableResourceTypeOptions, name }) => {
    it(`displays only the available resource types depending on the previous dataset : ${name}`, () => {
      cy.findByTestId(labelResourceType).parent().click();

      cy.contains(name).click();

      cy.findAllByTestId(labelSelectAResource).eq(0).click();

      cy.contains(`${name} 1`).click();

      cy.findByTestId(labelAddFilter).click();

      cy.findAllByTestId(labelResourceType).eq(1).parent().click();

      const availableResourceTypes = pluck(
        'name',
        availableResourceTypeOptions
      );

      availableResourceTypes.forEach((resourceType) => {
        cy.findByText(resourceType).should('be.visible');
      });

      difference(resourceTypesNames, availableResourceTypes).forEach(
        (resourceType) => {
          cy.findByLabelText(resourceType).should('not.exist');
        }
      );

      cy.makeSnapshot();
    });
  });

  it('removes all subsequent resource lines, if a resource type line in the middle of the tree is updated', () => {
    cy.findByTestId(labelResourceType).parent().click();

    cy.contains(labelHostCategory).click();

    cy.findByTestId(labelSelectAResource).click();

    cy.contains(`${labelHostCategory} 1`).click();

    cy.findByTestId(labelAddFilter).click();
    cy.findAllByTestId(labelResourceType).eq(1).parent().click();
    cy.contains(labelHostGroup).click();
    cy.findAllByTestId(labelSelectAResource).eq(1).click();
    cy.contains(`${labelHostGroup} 1`).click();

    cy.findByTestId(labelAddFilter).click();
    cy.findAllByTestId(labelResourceType).eq(2).parent().click();
    cy.contains(labelServiceCategory).click();
    cy.findAllByTestId(labelSelectAResource).eq(2).click();
    cy.contains(`${labelServiceCategory} 1`).click();

    cy.findAllByTestId(labelResourceType).should('have.length', 3);

    cy.findAllByTestId(labelResourceType).eq(1).parent().click();
    cy.contains(labelServiceGroup).click();

    cy.findAllByTestId(labelResourceType).should('have.length', 2);

    cy.makeSnapshot();
  });
});
