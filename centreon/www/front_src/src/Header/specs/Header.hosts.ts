import {
  labelDownStatusHosts,
  labelHosts,
  labelPendingStatusHosts,
  labelUnreachableStatusHosts,
  labelUpStatusHosts
} from '../Resources/Host/translatedLabels';
import { initialize } from './Header.utils';

const getElements = (): void => {
  cy.findByRole('link', { name: labelHosts, timeout: 5000 }).as('hostIcon');

  cy.findByRole('link', { name: labelDownStatusHosts }).as('downCounter');

  cy.findByRole('link', { name: labelUnreachableStatusHosts }).as(
    'unreachableCounter'
  );

  cy.findByRole('link', { name: labelUpStatusHosts }).as('upCounter');

  cy.findByRole('link', { name: labelPendingStatusHosts }).as('pendingCounter');
};

export default (): void =>
  describe(labelHosts, () => {
    describe('responsive behaviors', () => {
      it('displays the icon without an expand chevron', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@hostIcon').within(() => {
          cy.findByTestId('HostIcon').should('be.visible');
        });
        cy.findByTestId('ExpandMoreIcon').should('not.exist');
      });

      it('hides top counters when the screen is is under 600px width', () => {
        initialize();
        cy.viewport(599, 300);

        cy.findByRole('link', { name: labelHosts, timeout: 5000 }).should(
          'be.visible'
        );

        cy.findByRole('link', { name: labelDownStatusHosts }).should(
          'not.exist'
        );
        cy.findByRole('link', { name: labelUnreachableStatusHosts }).should(
          'not.exist'
        );
        cy.findByRole('link', { name: labelUpStatusHosts }).should('not.exist');
        cy.findByRole('link', { name: labelPendingStatusHosts }).should(
          'not.exist'
        );
      });
    });

    describe('Status counter', () => {
      it('displays formatted status counter numbers', () => {
        const hoststubs = {
          down: { unhandled: '12' },
          ok: '12134',
          pending: '7',
          unreachable: { unhandled: '126' }
        };

        initialize({ hosts_status: hoststubs });
        getElements();

        cy.get('@downCounter').should('be.visible').contains('12');
        cy.get('@unreachableCounter').should('be.visible').contains('126');
        cy.get('@upCounter').should('be.visible').contains('12.1k');
        cy.get('@pendingCounter').should('be.visible').contains('7');

        cy.makeSnapshot();
      });

      it('redirect to Resources Status with the correct filter when a counter is clicked', () => {
        const hoststubs = {
          down: { unhandled: '12' },
          ok: '12134',
          pending: '7',
          unreachable: { unhandled: '125' }
        };

        initialize({ hosts_status: hoststubs });
        getElements();

        cy.get('@downCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22host%22,%22name%22:%22Host%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22DOWN%22,%22name%22:%22Down%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@unreachableCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22host%22,%22name%22:%22Host%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22UNREACHABLE%22,%22name%22:%22Unreachable%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@upCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22host%22,%22name%22:%22Host%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22UP%22,%22name%22:%22Up%22}]},{%22name%22:%22states%22,%22value%22:[]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@pendingCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22host%22,%22name%22:%22Host%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22PENDING%22,%22name%22:%22Pending%22}]},{%22name%22:%22states%22,%22value%22:[]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );
      });
    });
  });
