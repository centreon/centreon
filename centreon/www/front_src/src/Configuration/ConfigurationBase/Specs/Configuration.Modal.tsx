import { labelSave } from '../translatedLabels';
import initialize, { mockModalRequests } from './initialize';
import { groups, inputs } from './utils';

export default (resourceType): void => {
  describe('Modal', () => {
    beforeEach(() => {
      mockModalRequests(resourceType.replace(' ', '_'));

      initialize({ resourceType });
    });

    describe('Creation mode', () => {
      it("opens the modal in creation mode when the 'Add' button was clicked", () => {
        cy.waitForRequest('@getAll');

        cy.get(`[data-testid="add-resource"]`).click();

        cy.contains(`Add a ${resourceType}`);

        cy.get(`button[data-testid="submit"`)
          .should('be.visible')
          .should('have.text', labelSave)
          .should('be.disabled');

        cy.makeSnapshot(
          `${resourceType}: opens the modal in creation mode when the 'Add' button was clicked`
        );

        cy.findByLabelText('close').click();
      });

      it('shows form fields organized into groups, with each field initialized with default values', () => {
        cy.waitForRequest('@getAll');

        cy.get(`[data-testid="add-resource"]`).click();

        groups.forEach(({ name }) => {
          cy.contains(name);
        });

        inputs.forEach(({ label }) => {
          cy.findAllByTestId(label)
            .eq(1)
            .should('be.visible')
            .should('have.value', '');
        });

        cy.findByLabelText('close').click();
      });

      it('sends a POST request when the Create Button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.get(`[data-testid="add-resource"]`).click();

        inputs.forEach(({ label }) => {
          cy.findAllByTestId(label).eq(1).clear().type(`${label} abc`);
        });

        cy.get(`button[data-testid="submit"`).click();

        cy.waitForRequest('@create').then(({ request }) => {
          expect(request.body).to.deep.equals({
            name: 'Name abc',
            alias: 'Alias abc',
            coordinates: 'Coordinates abc'
          });
        });

        cy.makeSnapshot(
          `${resourceType}: sends a Post request when the Create Button is clicked`
        );
      });
    });

    describe('Edition mode', () => {
      it('opens the modal in edition mode when the a listing row button was clicked', () => {
        cy.waitForRequest('@getAll');

        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.waitForRequest('@getDetails');

        cy.contains(`Modify a ${resourceType}`);

        cy.get(`button[data-testid="submit"`)
          .should('have.text', labelSave)
          .should('be.disabled');

        cy.makeSnapshot(
          `${resourceType}: opens the modal in edition mode when the a listing row button was clicked`
        );

        cy.findByLabelText('close').click();
      });

      it('shows form fields organized into groups, with each field initialized with the value received from the API', () => {
        cy.waitForRequest('@getAll');

        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.waitForRequest('@getDetails').then(({ response }) => {
          groups.forEach(({ name }) => {
            cy.contains(name);
          });

          inputs.forEach(({ fieldName, label }) => {
            cy.findAllByTestId(label)
              .eq(1)
              .should('have.value', response.body[fieldName]);
          });
        });

        cy.findByLabelText('close').click();
      });

      it('sends an UPDATE request when the Update Button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.waitForRequest('@getDetails');

        inputs.forEach(({ label }) => {
          cy.findAllByTestId(label).eq(1).clear().type(`${label} abc`);
        });

        cy.get(`button[data-testid="submit"`).click();

        cy.waitForRequest('@update').then(({ request }) => {
          expect(request.body).to.deep.equals({
            name: 'Name abc',
            alias: 'Alias abc',
            coordinates: 'Coordinates abc'
          });
        });

        cy.makeSnapshot(
          `${resourceType}: sends an update request when the Update Button is clicked`
        );
      });
    });
  });
};
