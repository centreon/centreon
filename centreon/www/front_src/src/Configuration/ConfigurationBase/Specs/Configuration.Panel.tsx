import { panelDataTestIds } from '../Panel/dataTestIds';
import { labelClose } from '../translatedLabels';
import initialize, { mockModalRequests } from './initialize';
import { groups, inputs } from './utils';

interface Options {
  // The panel is generic: a second resource type renders the same pixels with
  // another word in the title, so the baselines are taken once.
  hasSnapshots: boolean;
}

export default (resourceType, { hasSnapshots }: Options): void => {
  describe('Panel', () => {
    beforeEach(() => {
      mockModalRequests(resourceType.replace(' ', '_'));

      initialize({ formVariant: 'panel', resourceType });
    });

    describe('Creation mode', () => {
      it("opens the panel in creation mode when the 'Add' button was clicked", () => {
        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          `Add a ${resourceType}`
        );

        cy.get(`button[data-testid="${panelDataTestIds.save}"]`)
          .should('be.visible')
          .should('be.disabled');

        // The panel carries its actions in the header, so the form keeps none.
        cy.get('button[data-testid="submit"]').should('not.exist');
        cy.get('button[data-testid="cancel"]').should('not.exist');

        if (hasSnapshots) {
          cy.makeSnapshot(`${resourceType}: opens the panel in creation mode`);
        }

        cy.findByLabelText(labelClose).click();
      });

      it('opens over the listing, leaving it at its width', () => {
        cy.waitForRequest('@getAll');

        cy.get('.MuiTable-root').then(([listing]) => {
          const widthBeforeOpening = listing.getBoundingClientRect().width;

          cy.get('[data-testid="add-resource"]').click();

          cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
            'be.visible'
          );

          cy.get('.MuiTable-root').should(([listingWithPanelOpen]) => {
            expect(listingWithPanelOpen.getBoundingClientRect().width).to.equal(
              widthBeforeOpening
            );
          });
        });

        cy.findByLabelText(labelClose).click();
      });

      it('keeps the form actions inside a page too narrow for the panel', () => {
        cy.viewport(700, 590);

        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
          'be.visible'
        );
        cy.get(`button[data-testid="${panelDataTestIds.reset}"]`).should(
          'be.visible'
        );

        cy.findByLabelText(labelClose).click();
      });

      it('shows form fields organized into groups, with each field initialized with default values', () => {
        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        groups.forEach(({ name }) => {
          cy.contains(name);
        });

        inputs.forEach(({ label }) => {
          cy.findAllByTestId(label)
            .eq(1)
            .should('be.visible')
            .should('have.value', '');
        });

        cy.findByLabelText(labelClose).click();
      });

      it('sends a POST request when the save action is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        inputs.forEach(({ label }) => {
          cy.findAllByTestId(label).eq(1).clear().type(`${label} abc`);
        });

        cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

        cy.waitForRequest('@create').then(({ request }) => {
          expect(request.body).to.deep.equals({
            alias: 'Alias abc',
            coordinates: 'Coordinates abc',
            name: 'Name abc'
          });
        });
      });
    });

    describe('Edition mode', () => {
      it('opens the panel in edition mode when a listing row was clicked', () => {
        cy.waitForRequest('@getAll');

        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.waitForRequest('@getDetails');

        // The mock names the panel after the resource it holds.
        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          `${resourceType.replace(' ', '_')} 1`
        );

        cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
          'be.disabled'
        );
        cy.get(`button[data-testid="${panelDataTestIds.duplicate}"]`).should(
          'be.visible'
        );
        cy.get(`button[data-testid="${panelDataTestIds.delete}"]`).should(
          'be.visible'
        );

        if (hasSnapshots) {
          cy.makeSnapshot(`${resourceType}: opens the panel in edition mode`);
        }

        cy.findByLabelText(labelClose).click();
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

        cy.findByLabelText(labelClose).click();
      });

      it('sends an UPDATE request when the save action is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.waitForRequest('@getDetails');

        inputs.forEach(({ label }) => {
          cy.findAllByTestId(label).eq(1).clear().type(`${label} abc`);
        });

        cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

        cy.waitForRequest('@update').then(({ request }) => {
          expect(request.body).to.deep.equals({
            alias: 'Alias abc',
            coordinates: 'Coordinates abc',
            name: 'Name abc'
          });
        });
      });

      it('closes the panel when the close button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.contains(`${resourceType.replace(' ', '_')} 1`).click();

        cy.waitForRequest('@getDetails');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'be.visible'
        );

        cy.findByLabelText(labelClose).click();

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'not.exist'
        );
      });
    });
  });
};
