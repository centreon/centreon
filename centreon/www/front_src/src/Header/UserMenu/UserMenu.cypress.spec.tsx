import * as React from 'react';

import { BrowserRouter as Router } from 'react-router-dom';

import { centreonUi } from '../helpers';
import { Method } from '../../../../../cypress/support/commands';

import UserMenu from '.';

describe('User Menu', () => {
  beforeEach(() => {
    cy.fixture('userMenu').as('user');
    cy.interceptAPIRequest({
      alias: 'getUser',
      method: Method.GET,
      path: '**/internal.php',
      response: {
        autologinkey: null,
        fullname: 'admin admin',
        hasAccessToProfile: true,
        locale: 'en_US.UTF-8',
        password_remaining_time: 15369910,
        soundNotificationsEnabled: true,
        timezone: 'Europe/Paris',
        userId: '1',
        username: 'admin'
      }
    });

    cy.intercept('PATCH', 'parameters', {
      theme: 'dark'
    }).as('updateTheme');

    cy.stub(centreonUi, 'useLocaleDateTimeFormat').returns({
      format: (): string => 'April 28,2022',
      toTime: (): string => '4:20 PM'
    });

    cy.mount({
      Component: (
        <Router>
          <UserMenu />
        </Router>
      )
    });
  });

  it('matches the current snapshot "user menu"', () => {
    cy.viewport(1200, 1000);
    cy.get('[data-cy=userIcon]').as('userIcon').should('be.visible');
    cy.get('[data-cy=clock]').as('clock').should('be.visible');
    cy.get('@clock').contains('April 28,2022');
    cy.get('@clock').contains('4:20 PM');

    cy.matchImageSnapshot();
  });

  it('expect the clock to not be visible for a width less than 648px', () => {
    cy.viewport(640, 500);
    cy.get('[data-cy=clock]').as('clock').should('not.be.visible');

    cy.matchImageSnapshot();
  });

  it('expands the popper when the user icon is clicked', () => {
    cy.get('[data-cy=userIcon]').as('userIcon');
    cy.get('@userIcon').click();
    cy.get('[data-cy=popper]').as('popper').should('be.visible');
    cy.get('@popper').contains('admin');
    cy.get('@popper').contains('Dark');
    cy.get('@popper').contains('Light');
    cy.get('@popper').contains('Logout');

    cy.matchImageSnapshot();
  });

  it('changes style when switch is clicked', () => {
    cy.get('[data-cy=userIcon]').click();
    cy.get('[data-cy=themeSwitch]').as('switchMode').should('be.visible');
    cy.get('@switchMode').click();
    cy.matchImageSnapshot('User Menu -- using the dark mode');
    cy.get('@switchMode').click();

    cy.matchImageSnapshot('User Menu -- using the light mode');
  });
});
