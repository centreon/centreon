import { Provider } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/Text/moduleFederation.json';
import widgetText2Configuration from 'centreon-widgets/Text2/moduleFederation.json';

import { federatedWidgetsAtom } from '../federatedModules/atoms';

import Dashboard from '.';

describe('Dashboard', () => {
  beforeEach(() => {
    const federatedWidgets = [
      {
        ...widgetTextConfiguration,
        moduleFederationName: 'Text/src'
      },
      {
        ...widgetText2Configuration,
        moduleFederationName: 'Text2/src'
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
