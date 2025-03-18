import { Provider, createStore } from 'jotai';
import { BrowserRouter as Router } from 'react-router';

import { ThemeMode, userAtom } from '@centreon/ui-context';

import { labelCentreonLogo, labelMiniCentreonLogo } from '../translatedLabels';

import SideBar from './index';
import menuData from './tests/menuData.json';
import menuDataWithAdditionalLabel from './tests/menuDataWithAdditionalLabel.json';

const modes = [ThemeMode.dark, ThemeMode.light];

modes.forEach((mode) => {
  describe(`Navigation menu in ${mode} theme`, () => {
    beforeEach(() => {
      const store = createStore();
      store.set(userAtom, {
        alias: 'admin',
        defaultPage: '/monitoring/resources',
        isExportButtonEnabled: true,
        locale: 'en',
        name: 'admin',
        themeMode: mode,
        timezone: 'Europe/Paris',
        useDeprecatedPages: false
      });

      cy.mount({
        Component: (
          <Provider store={store}>
            <Router>
              <SideBar navigationData={menuData.result} />
            </Router>
          </Provider>
        )
      });
    });

    it(`matches the current snapshot "initial menu" in ${mode} theme`, () => {
      cy.findByAltText(labelMiniCentreonLogo).should('be.visible');
      cy.get('li').each((li) => {
        cy.wrap(li).get('svg').should('be.visible');
      });

      cy.makeSnapshot().then(() => {
        cy.findByLabelText(labelMiniCentreonLogo).click();
      });
    });

    it(`expands the menu when the logo is clicked in ${mode} theme`, () => {
      cy.findByLabelText(labelMiniCentreonLogo).click();
      cy.findByAltText(labelCentreonLogo).should('be.visible');
      cy.get('li').each((li, index) => {
        cy.wrap(li).as('element').get('svg').should('be.visible');
        if (index === 0) {
          cy.get('@element').contains('Monitoring');
        } else if (index === 1) {
          cy.get('@element').contains('Home');
        } else {
          cy.get('@element').contains('Configuration');
        }
      });

      cy.makeSnapshot();
    });

    it(`displays the direct child items and highlights the item when hovered in ${mode} theme`, () => {
      cy.get('li').eq(2).trigger('mouseover');
      cy.get('[data-cy=collapse]').should('be.visible');

      cy.makeSnapshot();
    });

    it(`highlights the menu item when double clicked in ${mode} theme`, () => {
      cy.get('li').eq(0).as('element').trigger('mouseover');
      cy.get('@element').trigger('dblclick');

      cy.makeSnapshot();
    });

    it(`highlights the parent item when the item is clicked in ${mode} theme`, () => {
      cy.findByLabelText(labelMiniCentreonLogo).click();
      cy.get('li').eq(2).trigger('mouseover');
      cy.get('[data-testid=ExpandMoreIcon]').should('be.visible');
      cy.get('[data-cy=collapse]').as('collapse').should('be.visible');
      cy.get('@collapse')
        .find('ul')
        .first()
        .as('first_element_collapse')
        .trigger('mouseover');
      cy.get('@first_element_collapse')
        .find('[data-testid=ExpandMoreIcon]')
        .should('be.visible');
      cy.get('@first_element_collapse')
        .find('[data-cy=collapse]')
        .as('second_collapse')
        .should('be.visible');
      cy.get('@second_collapse')
        .find('ul')
        .first()
        .trigger('mouseover')
        .trigger('click');

      cy.makeSnapshot();
    });
  });
});

describe('Navigation with additional label', () => {
  beforeEach(() => {
    const store = createStore();
    store.set(userAtom, {
      alias: 'admin',
      defaultPage: '/monitoring/resources',
      isExportButtonEnabled: true,
      locale: 'en',
      name: 'admin',
      themeMode: ThemeMode.light,
      timezone: 'Europe/Paris',
      useDeprecatedPages: false
    });

    cy.mount({
      Component: (
        <Provider store={store}>
          <Router>
            <SideBar navigationData={menuDataWithAdditionalLabel.result} />
          </Router>
        </Provider>
      )
    });
  });

  it('renders the menu item with additional label', () => {
    cy.get('li').eq(0).trigger('mouseover');

    cy.contains('Resources Status').should('be.visible');
    cy.contains('BETA').should('be.visible');
  });
});
