import { panelDataTestIds } from '../Panel/dataTestIds';
import { labelClose } from '../translatedLabels';
import initialize, {
  mockActionsRequests,
  mockModalRequests
} from './initialize';
import { groups, inputs } from './utils';

interface Options {
  // The panel is generic: a second resource type renders the same pixels with
  // another word in the title, so the baselines are taken once.
  hasSnapshots: boolean;
}

export default (resourceType, { hasSnapshots }: Options): void => {
  const resourceName = `${resourceType.replace(' ', '_')} 1`;

  describe('Panel', () => {
    const mount = (options = {}): void =>
      initialize({ formVariant: 'panel', resourceType, ...options });

    const openForEdition = (): void => {
      cy.waitForRequest('@getAll');

      cy.contains(resourceName).click();

      cy.waitForRequest('@getDetails');
    };

    beforeEach(() => {
      const resource = resourceType.replace(' ', '_');

      mockModalRequests(resource);
      mockActionsRequests(resource);
    });

    describe('Creation mode', () => {
      it("opens the panel in creation mode when the 'Add' button was clicked", () => {
        mount();

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
      });

      it('offers no resource action while creating', () => {
        mount();

        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        cy.get(`[data-testid="${panelDataTestIds.enable}"]`).should(
          'not.exist'
        );
        cy.get(`[data-testid="${panelDataTestIds.duplicate}"]`).should(
          'not.exist'
        );
        cy.get(`[data-testid="${panelDataTestIds.delete}"]`).should(
          'not.exist'
        );
      });

      it('opens over the listing, leaving it at its width', () => {
        mount();

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
      });

      it('keeps the panel and its actions inside a page too narrow for it', () => {
        cy.viewport(700, 590);

        mount();

        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        // Visibility alone would pass on a panel overflowing to the right:
        // Cypress does not consider where in the viewport an element sits.
        cy.window().then((window) => {
          cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
            ([panel]) => {
              expect(panel.getBoundingClientRect().right).to.be.at.most(
                window.innerWidth
              );
            }
          );

          cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
            ([save]) => {
              expect(save.getBoundingClientRect().right).to.be.at.most(
                window.innerWidth
              );
            }
          );
        });
      });

      it('shows form fields organized into groups, with each field initialized with default values', () => {
        mount();

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
      });

      it('sends a POST request when the save action is clicked', () => {
        mount();

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
        mount();

        openForEdition();

        // The mock names the panel after the resource it holds.
        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          resourceName
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
      });

      it('shows form fields organized into groups, with each field initialized with the value received from the API', () => {
        mount();

        cy.waitForRequest('@getAll');

        cy.contains(resourceName).click();

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
      });

      it('sends an UPDATE request when the save action is clicked', () => {
        mount();

        openForEdition();

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

      it('restores the loaded values when the reset action is clicked', () => {
        mount();

        openForEdition();

        cy.get(`button[data-testid="${panelDataTestIds.reset}"]`).should(
          'be.disabled'
        );

        cy.findAllByTestId('Name').eq(1).clear().type('edited');

        cy.get(`button[data-testid="${panelDataTestIds.reset}"]`).should(
          'be.enabled'
        );
        cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
          'be.enabled'
        );

        cy.get(`button[data-testid="${panelDataTestIds.reset}"]`).click();

        cy.findAllByTestId('Name').eq(1).should('have.value', resourceName);
        cy.get(`button[data-testid="${panelDataTestIds.reset}"]`).should(
          'be.disabled'
        );
        cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
          'be.disabled'
        );
      });

      it('disables the open resource from the panel header', () => {
        mount();

        openForEdition();

        cy.get(`[data-testid="${panelDataTestIds.enable}"] input`)
          .should('be.checked')
          .click();

        cy.waitForRequest('@disable').then(({ request }) => {
          expect(request.body).to.deep.equals({ ids: [1] });
        });
      });

      it('closes the panel and clears the URL when the open resource is deleted', () => {
        mount();

        openForEdition();

        cy.location('search').should('contain', 'id=1');

        cy.get(`button[data-testid="${panelDataTestIds.delete}"]`).click();

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@deleteOne');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'not.exist'
        );

        // A URL still naming the resource would open the panel on it again.
        cy.location('search').should('eq', '');
      });

      it('closes the panel when the close button is clicked', () => {
        mount();

        openForEdition();

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'be.visible'
        );

        cy.findByLabelText(labelClose).click();

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'not.exist'
        );
      });
    });

    describe('Deep linking', () => {
      it('opens the panel in creation mode from the URL', () => {
        mount({ searchParams: '?mode=add' });

        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          `Add a ${resourceType}`
        );
      });

      it('opens the panel on the resource named by the URL', () => {
        mount({ searchParams: '?mode=edit&id=1' });

        cy.waitForRequest('@getDetails');

        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          resourceName
        );
      });

      it('ignores the URL when the module offers neither edition nor details', () => {
        mount({
          actions: {
            delete: () => true,
            duplicate: () => true,
            edit: false,
            enableDisable: () => true,
            massive: true,
            viewDetails: false
          },
          searchParams: '?mode=edit&id=1'
        });

        cy.waitForRequest('@getAll');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'not.exist'
        );
      });
    });
  });
};
