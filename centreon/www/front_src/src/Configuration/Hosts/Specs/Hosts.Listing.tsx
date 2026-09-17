import { labelHosts, labelWelcomeToHosts } from '../translatedLabels';
import initialize from './initialize';

export default () => {
  describe('Listing: ', () => {
    it('renders the Hosts page with the ConfigurationBase layout', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains(labelHosts).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the hosts returned by the API', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').should('be.visible');
      cy.contains('10.0.0.0').should('be.visible');
      cy.contains('Central').should('be.visible');

      cy.makeSnapshot();
    });

    it('decodes a host with a null alias and no icon', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      // Host 1 carries a null alias, and no item carries an icon. Both must
      // decode, since API Platform omits null values entirely.
      cy.contains('host 1').should('be.visible');
      cy.contains('10.0.0.1').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the welcome page when no host exists', () => {
      initialize({ isEmpty: true });

      cy.waitForRequest('@getAllHosts');

      cy.contains(labelWelcomeToHosts).should('be.visible');

      cy.makeSnapshot();
    });
  });
};
