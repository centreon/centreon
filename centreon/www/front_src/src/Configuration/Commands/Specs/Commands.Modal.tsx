import initialize from './initialize';

import { labelAddCommand, labelName } from '../translatedLabels';

export default (): void => {
  describe('Modal', () => {
    beforeEach(() => {
      cy.viewport('macbook-15');

      initialize();
    });

    it('displays form fields with default values when the Modal is opened in Creation Mode', () => {
      cy.waitForRequest('@getCommands');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findByText(labelAddCommand).should('be.visible');

      cy.findAllByTestId(labelName)
        .eq(1)
        .should('be.visible')
        .should('have.value', '');

      cy.matchImageSnapshot();
    });
  });
};
