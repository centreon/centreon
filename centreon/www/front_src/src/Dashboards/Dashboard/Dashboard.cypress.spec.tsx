/* eslint-disable import/no-unresolved */

import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';

import { federatedWidgetsAtom } from '../../federatedModules/atoms';

import Dashboard from '.';

describe('Dashboard', () => {
  beforeEach(() => {
    const federatedWidgets = [
      {
        ...widgetTextConfiguration,
        moduleFederationName: 'centreon-widget-text/src'
      },
      {
        ...widgetInputConfiguration,
        moduleFederationName: 'centreon-widget-input/src'
      }
    ];

    cy.viewport('macbook-13');

    const store = createStore();
    store.set(federatedWidgetsAtom, federatedWidgets);

    cy.mount({
      Component: (
        <Provider store={store}>
          <Dashboard />
        </Provider>
      )
    });
  });

  it('tests something impressive', () => {
    cy.contains('Edit').click();

    cy.contains('Widget').click();

    cy.findAllByLabelText('Add a widget').eq(1).click();

    cy.contains('centreon-widget-text').click();

    cy.findByLabelText('Add').click();

    cy.contains('Widget').click();

    cy.findAllByLabelText('Add a widget').eq(1).click();

    cy.contains('centreon-widget-text2').click();

    cy.findByLabelText('Add').click();
  });
});
