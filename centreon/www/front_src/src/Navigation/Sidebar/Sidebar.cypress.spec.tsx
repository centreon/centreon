import React from 'react';

import { BrowserRouter as Router } from 'react-router-dom';
import { renderHook, act } from '@testing-library/react-hooks/dom';
import { useAtom } from 'jotai';

import { labelCentreonLogo, labelMiniCentreonLogo } from '../translatedLabels';

import { selectedNavigationItemsAtom } from './sideBarAtoms';

import SideBar from './index';

describe('Navigation menu', () => {
  beforeEach(() => {
    cy.fixture('menuData').then((data) => {
      return cy.mount({
        Component: (
          <Router>
            <SideBar navigationData={data.result} />
          </Router>
        )
      });
    });

    const { result } = renderHook(() => useAtom(selectedNavigationItemsAtom));

    act(() => {
      result.current[1](null);
    });
  });

  it('matches the current snapshot "initial menu"', () => {
    cy.findByAltText(labelMiniCentreonLogo).should('be.visible');
    cy.get('li').each(($li) => {
      cy.wrap($li).get('svg').should('be.visible');
    });

    cy.matchImageSnapshot().then(() => {
      cy.findByLabelText(labelMiniCentreonLogo).click();
    });
  });

  it('expands the menu when the logo is clicked', () => {
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

  it('displays the direct child items and highlights the item when hovered', () => {
    cy.get('li').eq(2).trigger('mouseover');
    cy.get('[data-cy=collapse]').should('be.visible');

    cy.matchImageSnapshot();
  });

  it('highlights the menu item when double clicked', () => {
    cy.get('li').eq(0).as('element').trigger('mouseover');
    cy.get('@element').trigger('dblclick');

    cy.matchImageSnapshot();
  });

  it('highlights the parent item when the item is clicked', () => {
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
