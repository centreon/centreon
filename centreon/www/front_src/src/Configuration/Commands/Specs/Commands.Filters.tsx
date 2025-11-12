import initialize from './initialize';

export default (): void => {
  describe('Filters', () => {
    beforeEach(initialize);
    it('displays the search bar component', () => {
      cy.waitForRequest('@getCommands');

      cy.get('[data-testid="search-bar"]').should('be.visible');

      cy.matchImageSnapshot();
    });
  });
};
