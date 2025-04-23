import {
  labelCopyAutologinLink,
  labelEditProfile,
  labelFullscreen,
  labelLogout
} from '../UserMenu/translatedLabels';

import { initialize } from './Header.utils';

export default (): void => {
  describe('User Menu', () => {
    beforeEach(() => {
      cy.intercept('PATCH', 'parameters', {
        theme: 'dark'
      }).as('updateTheme');
    });

    it('matches the current snapshot "user menu"', () => {
      initialize();
      cy.viewport(1200, 300);

      cy.get('[data-cy=clock]').as('clock');
      cy.get('@clock').should('be.visible');
      cy.get('@clock').contains('April 28, 2022');
      cy.get('@clock').contains('4:20 PM');

      cy.makeSnapshot();
    });

    it('does not display the clock for a width less than 769px', () => {
      initialize();
      cy.viewport(768, 500);
      cy.get('[data-cy=clock]').as('clock').should('not.be.visible');
      cy.makeSnapshot();
    });

    it('expands the popper when the user icon is clicked', () => {
      initialize();
      cy.get('[data-cy=userIcon]').as('userIcon');
      cy.get('@userIcon').click();
      cy.get('[data-cy=popper]').as('popper');

      cy.get('@popper').should('be.visible');
      cy.get('@popper').contains('admin');
      cy.get('@popper').contains('Dark');
      cy.get('@popper').contains('Light');
      cy.get('@popper').contains('Logout');

      cy.makeSnapshot();
    });

    it('changes style when switch is clicked', () => {
      initialize();
      cy.get('[data-cy=userIcon]').click();
      cy.get('[data-cy=themeSwitch]').as('switchMode').should('be.visible');
      cy.get('@switchMode').click();
      cy.get('header').should('have.css', 'background-color', 'rgb(0, 0, 0)');
      cy.makeSnapshot('User Menu -- using the dark mode');
      cy.get('@switchMode').click();
      cy.get('header').should(
        'have.css',
        'background-color',
        'rgb(37, 88, 145)'
      );
      cy.makeSnapshot('User Menu -- using the light mode');
    });

    it('navigates to the logout page when the button is clicked', () => {
      const navigate = initialize();

      cy.get('[data-cy=userIcon]').click();
      cy.contains(labelLogout)
        .click()
        .then(() => {
          expect(navigate).to.have.been.calledWith('/logout');
        });
    });

    it('copies the autologin link to the blipboard when the button is clicked', () => {
      initialize();

      cy.get('[data-cy=userIcon]').click();
      cy.contains(labelCopyAutologinLink).click();

      cy.window().then((win) => {
        win.navigator.clipboard.readText().then((text) => {
          expect(text).to.eq('LKEY-autologin');
        });
      });
    });

    it('navigates to the edit profile page when the button is clicked', () => {
      const navigates = initialize();

      cy.get('[data-cy=userIcon]').click();
      cy.contains(labelEditProfile)
        .click()
        .then(() => {
          expect(navigates).to.have.been.calledWith('/main.php?p=50104&o=c');
        });
    });

    it('closes the menu when the user icon is clicked once again', () => {
      initialize();

      cy.get('[data-cy=userIcon]').click();

      cy.contains(labelEditProfile).should('be.visible');

      cy.get('[data-cy=userIcon]').click();

      cy.contains(labelEditProfile).should('not.exist');

      cy.makeSnapshot();
    });

    it('enters fullscreen mode when the corresponding icon is clicked', () => {
      initialize();

      cy.get('[data-cy=userIcon]').click();

      cy.contains(labelFullscreen).realClick();

      cy.contains(labelFullscreen).should('not.exist');
    });
  });
};
