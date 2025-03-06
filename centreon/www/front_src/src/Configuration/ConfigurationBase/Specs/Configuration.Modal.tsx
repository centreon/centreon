import initialize from './initialize';

export default (resourceType): void => {
  describe('Modal', () => {
    beforeEach(() => initialize({ resourceType }));

    describe('Creation mode', () => {
      it("opens the modal in creation mode when the 'Add' button was clicked", () => {
        cy.get(`[data-testid="add-resource"]`).click();

        cy.contains(`Add a ${resourceType}`);

        cy.findByLabelText('close').click();
      });
    });

    describe('Edition mode', () => {
      it("opens the modal in creation mode when the 'Add' button was clicked", () => {
        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.contains(`Modify a ${resourceType}`);

        cy.findByLabelText('close').click();
      });
    });
  });
};
