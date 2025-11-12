import initialize from './initialize';

export default (): void => {
  describe('Listing', () => {
    beforeEach(initialize);
    it('displays the first page of the Commands listing', () => {
      cy.waitForRequest('@getCommands');

      cy.contains('check_host_alive');

      cy.matchImageSnapshot();
    });
  });
};
