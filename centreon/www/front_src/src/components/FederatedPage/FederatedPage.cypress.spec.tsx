import { Provider, createStore } from 'jotai';

import { federatedModulesAtom } from '@centreon/ui-context';

import FederatedPage from './FederatedPage';

const initialize = ({ route, hasChildren }): void => {
  const store = createStore();
  store.set(federatedModulesAtom, []);

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
  it('does not display a page when a route is set and the path is invalid', () => {
    initialize({ hasChildren: false, route: '/another-route' });

    cy.contains('Cannot load module').should('be.visible');

    cy.makeSnapshot();
  });
});
