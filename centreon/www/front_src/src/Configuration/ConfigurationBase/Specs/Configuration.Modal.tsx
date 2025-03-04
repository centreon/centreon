import initialize from './initialize';

export default (resourceType): void => {
  describe('Modal', () => {
    beforeEach(() => initialize({ resourceType }));

    describe('Creation mode', () => {
      it("opens the modal in creation mode when the 'Add' button was clicked", () => {
        cy.get(`[data-testid="add-resource"]`).click();

        cy.url().should('include', '/main.php?p=60102&o=a');
      });
    });

    describe('Edition mode', () => {
      it("opens the modal in creation mode when the 'Add' button was clicked", () => {
        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.url().should('include', '/main.php?p=60102&o=c&hg_id=1');
      });
    });
  });
};
