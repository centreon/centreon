import {
  labelAlias,
  labelIpAddress,
  labelMonitoringServer,
  labelName,
  labelTemplates
} from '../translatedLabels';
import initialize from './initialize';
import { hostIcon } from './utils';

export default () => {
  describe('Columns: ', () => {
    beforeEach(() => {
      initialize({});

      cy.waitForRequest('@getAllHosts');
    });

    it('displays every column of the listing', () => {
      [
        labelName,
        labelAlias,
        labelIpAddress,
        labelMonitoringServer,
        labelTemplates
      ].forEach((label) => {
        cy.contains(label).should('be.visible');
      });

      cy.contains('host 0').should('be.visible');
      cy.contains('alias for host 0').should('be.visible');
      cy.contains('10.0.0.0').should('be.visible');
      cy.contains('Central').should('be.visible');

      cy.makeSnapshot();
    });

    it('renders the icon returned by the API, at the URL it returned', () => {
      // Asserting the exact src is what proves no prefix is prepended: the
      // platform mount path is empty when Centreon is served at the root.
      cy.findByTestId('server.png')
        .should('be.visible')
        .and('have.attr', 'src', hostIcon);

      cy.makeSnapshot();
    });

    it('falls back to the default host icon when the host has no icon', () => {
      // `icon` is absent from host 1, and host 0 is the only row carrying one,
      // so exactly one default icon is left once host 0's image has loaded.
      cy.findByTestId('server.png').should('have.attr', 'src', hostIcon);

      cy.findAllByTestId('HostIcon').should('have.length', 1);

      cy.makeSnapshot();
    });

    it('links each template to its configuration page', () => {
      cy.findByTestId('host-template-link_5')
        .should('have.text', 'generic-active-host')
        .and('have.attr', 'href', '/main.php?p=60103&o=c&host_id=5');

      cy.findByTestId('host-template-link_6')
        .should('have.text', 'generic-passive-host')
        .and('have.attr', 'href', '/main.php?p=60103&o=c&host_id=6');
    });

    it('leaves the templates cell empty when the host inherits from none', () => {
      cy.contains('host 1').should('be.visible');

      cy.findByTestId('host-template-link_5').should('exist');
      cy.get('[data-testid^="host-template-link_"]').should('have.length', 2);
    });
  });
};
