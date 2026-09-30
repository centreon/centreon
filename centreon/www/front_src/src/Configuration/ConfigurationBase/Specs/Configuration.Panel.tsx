import { getDefaultPanelWidth, maxPanelWidth } from '../Panel';
import { panelDataTestIds } from '../Panel/dataTestIds';
import { labelClose, labelDelete, labelMoreActions } from '../translatedLabels';
import initialize, {
  mockActionsRequests,
  mockModalRequests
} from './initialize';
import { groups, inputs } from './utils';

interface Options {
  // The panel renders the same pixels whatever the resource type.
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

    const panelSurface = () =>
      cy
        .get(`[data-testid="${panelDataTestIds.content}"]`)
        .parents('.MuiPaper-root')
        .first();

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

        // The actions are in the header, so the form keeps none.
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

        // Nothing to reset, enable, duplicate or delete before it exists.
        cy.get(`[data-testid="${panelDataTestIds.reset}"]`).should('not.exist');
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

      it('runs from the top of the page to its bottom', () => {
        mount();

        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        cy.get('#page').then(([page]) => {
          const pageRect = page.getBoundingClientRect();

          // The rectangles below match the viewport too, so assert the
          // mechanism: without this the panel hangs off whatever ancestor
          // happens to be positioned, which is invisible in a test where the
          // page fills the frame.
          expect(getComputedStyle(page).position).to.equal('relative');

          // Above the page title, not below it.
          panelSurface().should(([panel]) => {
            const panelRect = panel.getBoundingClientRect();

            expect(panelRect.top).to.equal(pageRect.top);
            expect(panelRect.bottom).to.equal(pageRect.bottom);
            expect(panelRect.right).to.equal(pageRect.right);
          });
        });

        // The title it covers is still rendered underneath.
        cy.get('#header').should('exist');
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

          // Narrower than asked: the clamp fired, rather than 720 fitting.
          cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
            ([panel]) => {
              expect(panel.getBoundingClientRect().width).to.be.lessThan(720);
            }
          );
        });
      });

      // The screens the design sizes for, and what it opens the panel at.
      [
        { expected: 1400, viewport: 1920 },
        { expected: 900, viewport: 1512 },
        { expected: 900, viewport: 1440 },
        { expected: 720, viewport: 1280 }
      ].forEach(({ viewport, expected }) => {
        it(`opens at ${expected}px on a ${viewport}px screen`, () => {
          cy.viewport(viewport, 900);

          mount();

          cy.waitForRequest('@getAll');

          cy.get('[data-testid="add-resource"]').click();

          // The surface itself: a scrollbar narrows the content inside it.
          panelSurface().should(([panel]) => {
            expect(panel.getBoundingClientRect().width).to.equal(expected);
            expect(expected).to.equal(getDefaultPanelWidth(viewport));
          });
        });
      });

      it('reopens at the width it was last dragged to', () => {
        cy.viewport(1920, 900);

        mount({ panelWidth: 1000 });

        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        // Not the 1400 this screen opens at when nothing was ever dragged.
        panelSurface().should(([panel]) => {
          expect(panel.getBoundingClientRect().width).to.equal(1000);
        });

        cy.findByLabelText(labelClose).click();

        cy.get('[data-testid="add-resource"]').click();

        panelSurface().should(([panel]) => {
          expect(panel.getBoundingClientRect().width).to.equal(1000);
        });
      });

      it('opens no wider than the design allows, whatever the module asks for', () => {
        cy.viewport(1920, 900);

        mount({ formPanelWidth: 2000 });

        cy.waitForRequest('@getAll');

        cy.get('[data-testid="add-resource"]').click();

        panelSurface().should(([panel]) => {
          expect(panel.getBoundingClientRect().width).to.equal(maxPanelWidth);
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

        // Discarding edits is confirmed, as deleting and closing are.
        cy.findByTestId('confirm').click();

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

        cy.get(`[data-testid="${panelDataTestIds.enable}"] input`).should(
          'not.be.checked'
        );
      });

      it('puts the toggle back when the request is refused', () => {
        mockActionsRequests(resourceType.replace(' ', '_'), true);

        mount();

        openForEdition();

        cy.get(`[data-testid="${panelDataTestIds.enable}"] input`)
          .should('be.checked')
          .click();

        cy.waitForRequest('@disable');

        cy.get(`[data-testid="${panelDataTestIds.enable}"] input`).should(
          'be.checked'
        );
      });

      it('follows the listing when another row is clicked', () => {
        mount();

        openForEdition();

        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          resourceName
        );

        // No detail response for this one: the panel has only the row to go on.
        const otherResource = `${resourceType.replace(' ', '_')} 3`;

        cy.contains(otherResource).click();

        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          otherResource
        );
      });

      it('asks before another row takes the panel away from unsaved edits', () => {
        mount();

        openForEdition();

        cy.findAllByTestId('Name').eq(1).clear().type('edited');

        cy.contains(`${resourceType.replace(' ', '_')} 3`).click();

        cy.get('[role="dialog"]').should('be.visible');

        cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
          'have.text',
          resourceName
        );
      });

      it('closes the panel and clears the URL when the open resource is deleted', () => {
        mount();

        openForEdition();

        cy.location('search').should('contain', 'id=1');

        cy.get(`button[data-testid="${panelDataTestIds.delete}"]`).click();

        // Scoped: the listing row behind it carries the same text.
        cy.get('[role="dialog"]').contains(resourceName).should('be.visible');

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@deleteOne');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'not.exist'
        );

        cy.location('search').should('eq', '');
      });

      it('stays open when a delete does not name the resource it holds', () => {
        mount();

        openForEdition();

        // Rows are labelled by id; only activated ones are selectable.
        cy.findByLabelText('Select row 3').click();
        cy.findByLabelText('Select row 5').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();
        cy.findAllByTestId(labelDelete).eq(0).click();
        cy.findByTestId('confirm').click();

        cy.waitForRequest('@delete');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'be.visible'
        );
        cy.location('search').should('contain', 'id=1');
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

        // The URL is ignored, not the page.
        cy.contains(resourceName).should('be.visible');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'not.exist'
        );
      });

      it('opens a read-only panel for a module offering details without edition', () => {
        mount({
          actions: {
            delete: () => false,
            duplicate: () => false,
            edit: false,
            enableDisable: () => false,
            viewDetails: true
          },
          searchParams: '?mode=edit&id=1'
        });

        cy.waitForRequest('@getDetails');

        cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
          'be.visible'
        );

        [
          panelDataTestIds.save,
          panelDataTestIds.reset,
          panelDataTestIds.duplicate,
          panelDataTestIds.delete,
          panelDataTestIds.enable
        ].forEach((dataTestId) => {
          cy.get(`[data-testid="${dataTestId}"]`).should('not.exist');
        });
      });
    });
  });
};
