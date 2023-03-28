import { initialize } from './Header.testUtils';

export default (): void =>
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

      cy.matchImageSnapshot();
    });

    it('does not display the clock for a width less than 769px', () => {
      initialize();
      cy.viewport(768, 500);
      cy.get('[data-cy=clock]').as('clock').should('not.be.visible');
      cy.matchImageSnapshot();
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

      cy.matchImageSnapshot();
    });

    it('changes style when switch is clicked', () => {
      initialize();
      cy.get('[data-cy=userIcon]').click();
      cy.get('[data-cy=themeSwitch]').as('switchMode').should('be.visible');
      cy.get('@switchMode').click();
      cy.get('header').should('have.css', 'background-color', 'rgb(0, 0, 0)');
      cy.matchImageSnapshot('User Menu -- using the dark mode');
      cy.get('@switchMode').click();
      cy.get('header').should('have.css', 'background-color', 'rgb(37, 88, 145)');
      cy.matchImageSnapshot('User Menu -- using the light mode');
    });
  });
