import { labelHosts, labelWelcomeToHosts } from '../translatedLabels';
import initialize from './initialize';

export default () => {
  describe('Listing: ', () => {
    it('reads the listing from API Platform, not the legacy route', () => {
      initialize({});

      // The intercept glob matches the legacy base too, so assert the URL.
      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/hosts');
        expect(request.url.pathname).to.not.contain('/api/latest');
        expect(request.url.searchParams.get('page')).to.equal('1');
        expect(request.url.searchParams.get('itemsPerPage')).to.equal('10');
      });

      cy.contains(labelHosts).should('be.visible');
    });

    it('displays the hosts returned by the API', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').should('be.visible');
      cy.contains('10.0.0.0').should('be.visible');
      cy.contains('Central').should('be.visible');
    });

    it('decodes a host whose optional fields are absent from the response', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 1').should('be.visible');
      cy.contains('10.0.0.1').should('be.visible');
      cy.contains('alias for host 1').should('not.exist');
    });

    it('displays the welcome page when no host exists', () => {
      initialize({ isEmpty: true });

      cy.waitForRequest('@getAllHosts');

      cy.contains(labelWelcomeToHosts).should('be.visible');

      cy.makeSnapshot();
    });
  });
};
