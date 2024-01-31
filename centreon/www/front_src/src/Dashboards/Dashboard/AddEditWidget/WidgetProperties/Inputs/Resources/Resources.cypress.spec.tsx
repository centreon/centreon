import { Formik } from 'formik';
import { createStore, Provider } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';

import {
  singleHostPerMetricAtom,
  singleMetricSelectionAtom
} from '../../../atoms';
import { WidgetResourceType } from '../../../models';
import {
  labelResourceType,
  labelSelectAResource
} from '../../../../translatedLabels';
import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import Resources from './Resources';
import { resourceTypeBaseEndpoints } from './useResources';

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

const initialize = (): void => {
  const store = createStore();
  store.set(singleHostPerMetricAtom, true);
  store.set(singleMetricSelectionAtom, true);
  store.set(isEditingAtom, true);
  store.set(hasEditPermissionAtom, true);

  cy.interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.host]}**`,
    response: generateResources('Resource')
  });

  cy.interceptAPIRequest({
    alias: 'getServices',
    method: Method.GET,
    path: `**${resourceTypeBaseEndpoints[WidgetResourceType.service]}**`,
    response: generateResources('Resource')
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Formik
            initialValues={{
              data: {
                resources: []
              },
              moduleName: 'widget',
              options: {}
            }}
            onSubmit={cy.stub()}
          >
            <Resources propertyName="resources" />
          </Formik>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Resources', () => {
  it('displays host and service type when the corresponding atom is set to true', () => {
    initialize();

    cy.findAllByTestId(labelResourceType).eq(0).should('have.value', 'host');
    cy.findAllByTestId(labelResourceType).eq(1).should('have.value', 'service');

    cy.findAllByTestId(labelSelectAResource).eq(0).click();
    cy.waitForRequest('@getHosts');
    cy.contains('Resource 1').click();

    cy.findAllByTestId(labelSelectAResource).eq(1).click();
    cy.waitForRequest('@getServices');
    cy.contains('Resource 0').click();

    cy.findAllByTestId(labelSelectAResource)
      .eq(0)
      .should('have.value', 'Resource 1');
    cy.findAllByTestId(labelSelectAResource)
      .eq(1)
      .should('have.value', 'Resource 0');
  });
});
