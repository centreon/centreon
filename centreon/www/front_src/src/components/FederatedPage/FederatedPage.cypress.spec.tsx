// eslint-disable-next-line import/no-unresolved
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import { Provider, createStore } from 'jotai';

import { federatedModulesAtom } from '@centreon/ui-context';

import FederatedPage from './FederatedPage';

const initialize = ({ route, hasChildren }): void => {
  const store = createStore();

  store.set(federatedModulesAtom, [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    }
  ]);

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <Provider store={store}>
          <FederatedPage
            childrenComponent={hasChildren ? 'test' : undefined}
            route={route}
          />
        </Provider>
      </div>
    )
  });
};

describe('Federated page', () => {
  it('displays a page when a route is set and valid', () => {
    initialize({ hasChildren: false, route: '/text' });

    cy.contains('Hello world').should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display a page when a route is set and the path is invalid', () => {
    initialize({ hasChildren: false, route: '/another-route' });

    cy.contains('Cannot load module').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a page with a children component when a route and children component are set', () => {
    initialize({ hasChildren: true, route: '/text' });

    cy.contains('Hello world').should('be.visible');
    cy.contains('children component').should('be.visible');

    cy.makeSnapshot();
  });
});
