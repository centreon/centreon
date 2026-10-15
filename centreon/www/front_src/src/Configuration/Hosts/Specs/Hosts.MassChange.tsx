import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelClose,
  labelFailedToUpdateSomeResources,
  labelMassChange,
  labelMoreActions
} from '../../ConfigurationBase/translatedLabels';
import {
  labelDown,
  labelTemplateReplacementDeletesServices
} from '../translatedLabels';
import initialize, { patchedHostIds } from './initialize';

// By its own checkbox: the Enable/Disable switches are checkboxes too.
const tickRow = (id: number): void => {
  cy.get(`[aria-label="Select row ${id}"]`).click();
};

const openMassChange = (): void => {
  cy.findByTestId(labelMoreActions).click();
  cy.get('[role="menu"]').contains(labelMassChange).click();
};

const save = (): void => {
  cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();
};

const typeMaxCheckAttempts = (value: string): void => {
  cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
    .eq(1)
    .type(value);
};

// Exactly these hosts were written to, as many times, and through API
// Platform.
const expectPatchedHosts = (
  expectedIds: Array<number>,
  body?: object
): void => {
  [...new Set(expectedIds)].forEach((id) => {
    cy.waitForRequest(id === 0 ? '@patchHost' : `@patchHost${id}`).then(
      ({ request }) => {
        expect(request.url.pathname).to.match(
          new RegExp(`/api/configuration/hosts/${id}$`)
        );
        expect(request.url.pathname).to.not.contain('/api/latest');

        if (body) {
          expect(request.body).to.deep.equal(body);
        }
      }
    );
  });

  cy.wrap(patchedHostIds).should((ids) => {
    expect([...ids].sort(), 'hosts sent a PATCH').to.deep.equal(
      [...expectedIds].sort()
    );
  });
};

export default () => {
  describe('Mass change: ', () => {
    it('applies the changes to the hosts ticked one by one, sending only what changed', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      tickRow(2);

      openMassChange();

      cy.findByTestId(panelDataTestIds.header).should(
        'contain.text',
        `${labelMassChange} (2)`
      );
      // Nothing that identifies one host, and no macros yet.
      cy.findByTestId('host-form-name').should('not.exist');
      cy.findByTestId('host-form-address').should('not.exist');
      cy.findByTestId('host-form-check-options-macros').should('not.exist');

      // Incremental, the default: added to each host's own.
      cy.findByTestId('host-form-groups').click();
      cy.get('.MuiAutocomplete-popper').contains('Linux servers').click();
      cy.findByTestId(`host-form-notifications-options-${labelDown}`).click();

      // Replacement with nothing picked clears the categories.
      cy.findByTestId('host-form-update-mode-categories-replacement').click();

      typeMaxCheckAttempts('3');

      save();

      cy.findByTestId('mass-change-confirmation').should(
        'have.text',
        'The changes will be applied to 2 hosts.'
      );
      cy.findByTestId('mass-change-warning').should('not.exist');

      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 2], {
        category_ids: [],
        host_group_ids_to_add: [1],
        notifications: { options_to_add: ['down'] },
        scheduling_options: { max_check_attempts: 3 }
      });

      cy.contains('Hosts updated').should('be.visible');
      cy.findByTestId(panelDataTestIds.header).should('not.exist');
    });

    it('applies the changes to every host the check-all selects', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[aria-label="Select all"]').click();

      openMassChange();

      // The deactivated host 1 is selected too, although its own checkbox is
      // greyed: known, and out of this ticket.
      cy.findByTestId(panelDataTestIds.header).should(
        'contain.text',
        `${labelMassChange} (3)`
      );

      typeMaxCheckAttempts('3');

      save();

      cy.findByTestId('mass-change-confirmation').should(
        'have.text',
        'The changes will be applied to 3 hosts.'
      );
      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 1, 2], {
        scheduling_options: { max_check_attempts: 3 }
      });
    });

    it('writes nothing until the confirmation is accepted', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      openMassChange();

      typeMaxCheckAttempts('3');
      save();

      cy.findByTestId('cancel').click();

      cy.findByTestId(panelDataTestIds.header).should(
        'contain.text',
        `${labelMassChange} (1)`
      );
      expectPatchedHosts([]);
    });

    it('warns that replacing the templates deletes their services', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      tickRow(2);
      openMassChange();

      typeMaxCheckAttempts('3');
      save();

      // Adding templates deletes nothing.
      cy.findByTestId('mass-change-warning').should('not.exist');
      cy.findByTestId('cancel').click();

      cy.findByTestId('host-form-update-mode-templates-replacement').click();
      save();

      cy.findByTestId('mass-change-warning')
        .should('have.attr', 'role', 'alert')
        .and('contain.text', labelTemplateReplacementDeletesServices);

      cy.makeSnapshot();

      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 2], {
        scheduling_options: { max_check_attempts: 3 },
        template_ids: []
      });
    });

    it('leaves the additive inheritance alone unless Yes or No is picked', () => {
      initialize({ isAdditiveInheritanceEnabled: true });

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      openMassChange();

      cy.findByTestId(
        'host-form-notifications-contact-additive-inheritance-true'
      ).should('have.attr', 'aria-pressed', 'false');
      cy.findByTestId(
        'host-form-notifications-contact-additive-inheritance-false'
      ).should('have.attr', 'aria-pressed', 'false');

      // Off on purpose, which a switch could not tell from "unchanged".
      cy.findByTestId(
        'host-form-notifications-contact-additive-inheritance-false'
      ).click();

      save();
      cy.findByTestId('confirm').click();

      expectPatchedHosts([0], {
        notifications: { contact_additive_inheritance: false }
      });
    });

    it('names the hosts that failed and keeps the panel open on them alone', () => {
      initialize({ patchFails: [2] });

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      tickRow(2);
      openMassChange();

      typeMaxCheckAttempts('3');
      save();
      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 2]);

      cy.contains(`${labelFailedToUpdateSomeResources}: host 2`).should(
        'be.visible'
      );

      // Still open on what was typed, now aimed at the failed host only.
      cy.findByTestId(panelDataTestIds.header).should(
        'contain.text',
        `${labelMassChange} (1)`
      );
      cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
        .eq(1)
        .should('have.value', '3');

      save();
      cy.findByTestId('mass-change-confirmation').should(
        'have.text',
        'The changes will be applied to 1 host.'
      );
      cy.findByTestId('confirm').click();

      // Host 0 is not written to again.
      expectPatchedHosts([0, 2, 2]);
    });

    it('keeps the panel open on every host when they all fail', () => {
      initialize({ patchFails: [0, 2] });

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      tickRow(2);
      openMassChange();

      typeMaxCheckAttempts('3');
      save();
      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 2]);

      cy.contains('Failed to update the hosts').should('be.visible');
      cy.contains('Hosts updated').should('not.exist');
      cy.findByTestId(panelDataTestIds.header).should(
        'contain.text',
        `${labelMassChange} (2)`
      );
      cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
        .eq(1)
        .should('have.value', '3');
    });

    it('asks for the confirmation when the changes are saved on closing', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      tickRow(2);
      openMassChange();

      cy.findByTestId('host-form-update-mode-templates-replacement').click();

      cy.findByLabelText(labelClose).click();
      // The unsaved changes dialog's own Save.
      cy.findByTestId('confirm').click();

      // Not written yet: the count, the hosts and the warning come first.
      cy.findByTestId('mass-change-confirmation').should(
        'have.text',
        'The changes will be applied to 2 hosts.'
      );
      cy.findByTestId('mass-change-warning').should('be.visible');
      expectPatchedHosts([]);

      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 2], { template_ids: [] });
    });

    it('requires no host group on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      tickRow(0);
      tickRow(2);
      openMassChange();

      typeMaxCheckAttempts('3');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
        'be.enabled'
      );
      save();
      cy.findByTestId('confirm').click();

      expectPatchedHosts([0, 2], {
        scheduling_options: { max_check_attempts: 3 }
      });
    });
  });
};
