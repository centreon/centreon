import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { ListingVariant, ThemeMode, userAtom } from '@centreon/ui-context';

import navigationAtom from '../Navigation/navigationAtoms';

import Breadcrumbs from '.';

const initializeComponent = (fixture = 'menuData'): void => {
  cy.fixture(fixture).then((data) => {
    const store = createStore();

    store.set(userAtom, {
      alias: 'admin',
      default_page: '/monitoring/resources',
      isExportButtonEnabled: true,
      locale: 'en',
      name: 'admin',
      themeMode: ThemeMode.light,
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: ListingVariant.compact
    });
    store.set(navigationAtom, data);

    cy.mount({
      Component: (
        <BrowserRouter>
          <Provider store={store}>
            <Breadcrumbs path="/monitoring/resources" />
          </Provider>
        </BrowserRouter>
      )
    });
  });
};

describe('BreadcrumbTrail', () => {
  it('displays the breadcrumb trail', () => {
    initializeComponent();
    cy.get('a')
      .eq(0)
      .should('have.text', 'Monitoring')
      .should('have.attr', 'href', '/monitoring/resources');
    cy.get('a')
      .eq(1)
      .should('have.text', 'Resource Status')
      .should('have.attr', 'href', '/monitoring/resources');

    cy.makeSnapshot();
  });

  it('displays the breadcrumb trail with an additional label', () => {
    initializeComponent('menuDataWithAdditionalLabel');

    cy.contains('BETA').should('be.visible');

    cy.makeSnapshot();
  });
});
