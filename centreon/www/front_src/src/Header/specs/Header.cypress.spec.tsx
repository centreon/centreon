import { initialize } from './Header.testUtils';
import HeaderServicesSpecs from './Header.Services';
import HeaderHostsSpecs from './Header.Hosts';
import HeaderUserMenu from './Header.UserMenu';
import HeaderPollers from './Header.Poller';

describe('Header', () => {
  it('renders all top menus items', () => {
    initialize();
    cy.viewport(1024, 300);
    cy.findByRole('button', { name: 'Services', timeout: 10000 }).should(
      'be.visible'
    );
    cy.findByRole('button', { name: 'Hosts', timeout: 10000 }).should(
      'be.visible'
    );
    cy.findByRole('button', { name: 'Pollers', timeout: 10000 }).should(
      'be.visible'
    );
    cy.get('[aria-label="Profile"]', { timeout: 10000 }).should('not.exist');
    cy.matchImageSnapshot();
  });

  it('modify layout when viewport width is smaller than 769px', () => {
    initialize();
    cy.viewport(768, 300);
    cy.findByRole('button', { name: 'Services', timeout: 10000 }).should(
      'be.visible'
    );
    cy.findByRole('button', { name: 'Hosts', timeout: 10000 }).should(
      'be.visible'
    );
    cy.findByRole('button', { name: 'Pollers', timeout: 10000 }).should(
      'be.visible'
    );
    cy.get('[aria-label="Profile"]', { timeout: 10000 }).should('be.visible');
    cy.matchImageSnapshot();
  });

  it('modify layout when viewport width is smaller than 600 px', () => {
    initialize();
    cy.viewport(599, 300);
    cy.findByRole('button', { name: 'Services', timeout: 10000 }).should(
      'be.visible'
    );
    cy.findByRole('button', { name: 'Hosts', timeout: 10000 }).should(
      'be.visible'
    );
    cy.findByRole('button', { name: 'Pollers', timeout: 10000 }).should(
      'be.visible'
    );
    cy.get('[aria-label="Profile"]', { timeout: 10000 }).should('be.visible');
    cy.matchImageSnapshot();
  });

  HeaderPollers();
  HeaderServicesSpecs();
  HeaderHostsSpecs();
  HeaderUserMenu();
});
