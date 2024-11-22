import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { ListingVariant, ThemeMode, userAtom } from '@centreon/ui-context';

import navigationAtom from '../Navigation/navigationAtoms';

import { SnackbarProvider } from '@centreon/ui';
import Breadcrumbs, { router } from '.';
import { labelBreadcrumbCopied, labelCopyBreadcrumb } from './translatedLabels';

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

    cy.stub(router, 'useLocation').returns({
      pathname: '/monitoring/resources'
    });

    cy.window()
      .its('navigator.clipboard')
      .then((clipboard) => {
        cy.stub(clipboard, 'writeText').as('writeText');
      });

    cy.mount({
      Component: (
        <SnackbarProvider>
          <BrowserRouter>
            <Provider store={store}>
              <Breadcrumbs />
            </Provider>
          </BrowserRouter>
        </SnackbarProvider>
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
      .should('have.text', 'Resources Status')
      .should('have.attr', 'href', '/monitoring/resources');

    cy.makeSnapshot();
  });

  it('displays the breadcrumb trail with an additional label', () => {
    initializeComponent('menuDataWithAdditionalLabel');

    cy.contains('BETA').should('be.visible');

    cy.makeSnapshot();
  });

  it('copies the breadcrumb when the breadcrumd is being hovered and an icon is clicked', () => {
    initializeComponent();

    cy.contains('Monitoring').realHover();

    cy.findByLabelText(labelCopyBreadcrumb).should('have.css', 'opacity', '1');

    cy.findByLabelText(labelCopyBreadcrumb).click();

    cy.contains(labelBreadcrumbCopied).should('be.visible');

    cy.get('@writeText').should(
      'have.been.calledWith',
      'Monitoring > Resources Status'
    );

    cy.makeSnapshot();
  });
});
