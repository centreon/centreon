import React from 'react';

import { BrowserRouter as Router } from 'react-router-dom';
import { renderHook, act } from '@testing-library/react-hooks/dom';
import { Provider, useAtom, useAtomValue } from 'jotai';

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

        return cy.mount({
          Component: (
            <Provider initialValues={[[userAtom, userData]]}>
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

      cy.matchImageSnapshot().then(() => {
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

      cy.matchImageSnapshot();
    });

    it(`displays the direct child items and highlights the item when hovered in ${mode} theme`, () => {
      cy.get('li').eq(2).trigger('mouseover');
      cy.get('[data-cy=collapse]').should('be.visible');

      cy.matchImageSnapshot();
    });

    it(`highlights the menu item when double clicked in ${mode} theme`, () => {
      cy.get('li').eq(0).as('element').trigger('mouseover');
      cy.get('@element').trigger('dblclick');

      cy.matchImageSnapshot();
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

      cy.matchImageSnapshot();
    });
  });
});
