import { initialize } from './Header.testUtils';
import HeaderUserMenu from './Header.UserMenu';

describe('Header', () => {
  it('should renders all top menus items', () => {
    initialize();
    cy.viewport(1024, 300);
    cy.findByRole('button', { name: 'Services', timeout: 5000 });
    cy.findByRole('button', { name: 'Hosts', timeout: 5000 });
    cy.findByRole('button', { name: 'Pollers', timeout: 5000 });
    cy.get('[aria-label="Profile"]', { timeout: 5000 }).should('be.visible');
    cy.matchImageSnapshot();
    cy.viewport(768, 300);
    cy.matchImageSnapshot();
    cy.viewport(599, 300);
    cy.matchImageSnapshot();
  });

  HeaderUserMenu();
});
