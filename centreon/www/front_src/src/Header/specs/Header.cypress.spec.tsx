import HeaderHostsSpecs from './Header.hosts';
import HeaderPollers from './Header.poller';
import HeaderServicesSpecs from './Header.services';
import { initialize } from './Header.utils';
import HeaderUserMenu from './Header.usermenu';

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
    cy.get('[aria-label="Profile"]', { timeout: 10000 }).should('be.visible');
    cy.makeSnapshot();
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
    cy.makeSnapshot();
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
    cy.makeSnapshot();
  });

  HeaderPollers();
  HeaderServicesSpecs();
  HeaderHostsSpecs();
  HeaderUserMenu();
});
