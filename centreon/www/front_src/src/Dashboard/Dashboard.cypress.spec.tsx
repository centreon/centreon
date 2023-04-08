import { Provider } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetText2Configuration from 'centreon-widgets/centreon-widget-text2/moduleFederation.json';

import { federatedWidgetsAtom } from '../federatedModules/atoms';

import Dashboard from '.';

describe('Dashboard', () => {
  beforeEach(() => {
    const federatedWidgets = [
      {
        ...widgetTextConfiguration,
        moduleFederationName: 'centreon-widget-text/src'
      },
      {
        ...widgetText2Configuration,
        moduleFederationName: 'centreon-widget-text2/src'
      }
    ];

    cy.viewport('macbook-13');

    cy.mount({
      Component: (
        <Provider initialValues={[[federatedWidgetsAtom, federatedWidgets]]}>
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
