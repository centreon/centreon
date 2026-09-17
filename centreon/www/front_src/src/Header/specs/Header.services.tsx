import {
  labelCriticalStatusServices,
  labelOkStatusServices,
  labelPendingStatusServices,
  labelServices,
  labelUnknownStatusServices,
  labelWarningStatusServices
} from '../Resources/Service/translatedLabels';
import { initialize } from './Header.utils';

const getElements = (): void => {
  cy.findByRole('link', { name: labelServices, timeout: 5000 }).as(
    'serviceIcon'
  );

  cy.findByRole('link', { name: labelCriticalStatusServices }).as(
    'criticalCounter'
  );

  cy.findByRole('link', { name: labelUnknownStatusServices }).as(
    'unknownCounter'
  );

  cy.findByRole('link', { name: labelOkStatusServices }).as('okCounter');

  cy.findByRole('link', { name: labelWarningStatusServices }).as(
    'warningCounter'
  );

  cy.findByRole('link', { name: labelPendingStatusServices }).as(
    'pendingCounter'
  );
};

export default (): void =>
  describe(labelServices, () => {
    describe('responsive behaviors', () => {
      it('displays the icon without an expand chevron', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@serviceIcon').within(() => {
          cy.findByTestId('GrainIcon').should('be.visible');
          cy.findByTestId('ExpandMoreIcon').should('not.exist');
        });
      });

      it('hides top counters viewport size under 600px', () => {
        initialize();
        cy.viewport(599, 300);
        cy.findByRole('link', { name: labelServices, timeout: 5000 }).should(
          'be.visible'
        );

        cy.findByRole('link', { name: labelCriticalStatusServices }).should(
          'not.exist'
        );
        cy.findByRole('link', { name: labelUnknownStatusServices }).should(
          'not.exist'
        );
        cy.findByRole('link', { name: labelOkStatusServices }).should(
          'not.exist'
        );
        cy.findByRole('link', { name: labelWarningStatusServices }).should(
          'not.exist'
        );
        cy.findByRole('link', { name: labelPendingStatusServices }).should(
          'not.exist'
        );
      });
    });

    describe('top status counter', () => {
      it('displays the status counter numbers with the desired format', () => {
        const serviceStubs = {
          critical: { unhandled: '12' },
          ok: '12134',
          pending: '3',
          unknown: { unhandled: '126' },
          warning: { unhandled: '14688222' }
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        cy.get('@criticalCounter').should('be.visible').contains('12');
        cy.get('@unknownCounter').should('be.visible').contains('126');
        cy.get('@okCounter').should('be.visible').contains('12.1k');
        cy.get('@warningCounter').should('be.visible').contains('14.7m');
        cy.get('@pendingCounter').should('be.visible').contains('3');

        cy.makeSnapshot();
      });

      it('redirects on click on the counter', () => {
        const serviceStubs = {
          critical: { unhandled: '12' },
          ok: '12134',
          pending: '3',
          unknown: { unhandled: '125' },
          warning: { unhandled: '14688222' }
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        cy.get('@criticalCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22CRITICAL%22,%22name%22:%22Critical%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@unknownCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22UNKNOWN%22,%22name%22:%22Unknown%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@okCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22OK%22,%22name%22:%22Ok%22}]},{%22name%22:%22states%22,%22value%22:[]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@warningCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22WARNING%22,%22name%22:%22Warning%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@pendingCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22PENDING%22,%22name%22:%22Pending%22}]},{%22name%22:%22states%22,%22value%22:[]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );
      });
    });
  });
