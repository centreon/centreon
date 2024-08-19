import { act, renderHook } from '@testing-library/react-hooks/dom';
import { Provider, createStore, useAtom, useAtomValue } from 'jotai';
import { BrowserRouter as Router } from 'react-router-dom';

import { ThemeMode, userAtom } from '@centreon/ui-context';

import { labelCentreonLogo, labelMiniCentreonLogo } from '../translatedLabels';

import { selectedNavigationItemsAtom } from './sideBarAtoms';

import SideBar from './index';

const modes = [ThemeMode.dark, ThemeMode.light];

modes.forEach((mode) => {
  describe(`Navigation menu in ${mode} theme`, () => {
    beforeEach(() => {
      cy.fixture('menuData').then((data) => {
        const userData = renderHook(() => useAtomValue(userAtom));
        userData.result.current.themeMode = mode;

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

        return cy.mount({
          Component: (
            <Provider store={store}>
              <Router>
                <SideBar navigationData={data.result} />
              </Router>
            </Provider>
          )
        });
      });

      const { result } = renderHook(() => useAtom(selectedNavigationItemsAtom));

      act(() => {
        result.current[1](null);
      });
    });

    it(`matches the current snapshot "initial menu" in ${mode} theme`, () => {
      cy.findByAltText(labelMiniCentreonLogo).should('be.visible');
      cy.get('li').each(($li) => {
        cy.wrap($li).get('svg').should('be.visible');
      });

      cy.makeSnapshot().then(() => {
        cy.findByLabelText(labelMiniCentreonLogo).click();
      });
    });

    it(`expands the menu when the logo is clicked in ${mode} theme`, () => {
      cy.findByLabelText(labelMiniCentreonLogo).click();
      cy.findByAltText(labelCentreonLogo).should('be.visible');
      cy.get('li').each(($li, index) => {
        cy.wrap($li).as('element').get('svg').should('be.visible');
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
    cy.fixture('menuDataWithAdditionalLabel').then((data) => {
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

      return cy.mount({
        Component: (
          <Provider store={store}>
            <Router>
              <SideBar navigationData={data.result} />
            </Router>
          </Provider>
        )
      });
    });

    const { result } = renderHook(() => useAtom(selectedNavigationItemsAtom));

    act(() => {
      result.current[1](null);
    });
  });

  it('renders the menu item with additional label', () => {
    cy.get('li').eq(0).trigger('mouseover');

    cy.contains('Resources Status').should('be.visible');
    cy.contains('BETA').should('be.visible');
  });
});
