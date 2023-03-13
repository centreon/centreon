import {
  labelCriticalStatusServices,
  labelWarningStatusServices,
  labelUnknownStatusServices,
  labelOkStatusServices,
  labelCritical,
  labelAll,
  labelOk,
  labelPending,
  labelUnknown,
  labelWarning,
  labelServices
} from '../Resources/Service/translatedLabels';

import {
  initialize,
  submenuShouldBeClosed,
  submenuShouldBeOpened,
  openSubMenu
} from './Header.testUtils';

const getElements = (): void => {
  cy.findByRole('button', { name: labelServices, timeout: 5000 }).as(
    'serviceButton'
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
};

export default (): void =>
  describe(labelServices, () => {
    describe('responsive behaviors', () => {
      it('hides the button’s text at viewports uneder 768px', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText(labelServices).should('be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('GrainIcon').should('be.visible');
        });

        cy.viewport(767, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText(labelServices).should('not.be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('GrainIcon').should('be.visible');
        });
      });

      it('hides top counters viewport size under 600px', () => {
        initialize();
        getElements();

        cy.viewport(599, 300);
        cy.get('@criticalCounter').should('not.be.visible');
        cy.get('@unknownCounter').should('not.be.visible');
        cy.get('@okCounter').should('not.be.visible');
        cy.get('@warningCounter').should('not.be.visible');
      });
    });

    describe('pending indicator', () => {
      it('displays a pending indicator when the pending count is greater than 0', () => {
        const serviceStubs = {
          pending: '1'
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        cy.get('@serviceButton').within(() => {
          cy.get('.MuiBadge-badge.MuiBadge-colorPending')
            .should('exist')
            .should('be.visible');
        });
      });

      it('hides the pending indicator when there is no pending ressources', () => {
        const serviceStubs = {
          pending: '0'
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        cy.get('@serviceButton').within(() => {
          cy.get('.MuiBadge-badge.MuiBadge-colorPending')
            .should('exist')
            .should('not.be.visible');
        });
      });
    });

    describe('top status counter', () => {
      it('displays the status counter numbers with the desired format', () => {
        const serviceStubs = {
          critical: { unhandled: '12' },
          ok: '12134',
          unknown: { unhandled: '126' },
          warning: { unhandled: '14688222' }
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        cy.get('@criticalCounter').should('be.visible').contains('12');
        cy.get('@unknownCounter').should('be.visible').contains('126');
        cy.get('@okCounter').should('be.visible').contains('12.1k');
        cy.get('@warningCounter').should('be.visible').contains('14.7m');

        cy.matchImageSnapshot();
      });

      it('redirects on click on the counter', () => {
        // given
        const serviceStubs = {
          critical: { unhandled: '12' },
          ok: '12134',
          unknown: { unhandled: '125' },
          warning: { unhandled: '14688222' }
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        cy.get('@criticalCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22service%22,%22name%22:%22Service%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22CRITICAL%22,%22name%22:%22Critical%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@unknownCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22service%22,%22name%22:%22Service%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22UNKNOWN%22,%22name%22:%22Unknown%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@okCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22service%22,%22name%22:%22Service%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22OK%22,%22name%22:%22Ok%22}]},{%22name%22:%22states%22,%22value%22:[]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );

        cy.get('@warningCounter').click();

        cy.url().should(
          'include',
          'monitoring/resources?filter={%22criterias%22:[{%22name%22:%22resource_types%22,%22value%22:[{%22id%22:%22service%22,%22name%22:%22Service%22}]},{%22name%22:%22statuses%22,%22value%22:[{%22id%22:%22WARNING%22,%22name%22:%22Warning%22}]},{%22name%22:%22states%22,%22value%22:[{%22id%22:%22unhandled_problems%22,%22name%22:%22Unhandled%22}]},{%22name%22:%22search%22,%22value%22:%22%22}]}&fromTopCounter=true'
        );
      });
    });

    describe('sub menu', () => {
      it('displays a button to open the submenu', () => {
        initialize();
        getElements();
        submenuShouldBeClosed(labelServices);
        cy.get('@serviceButton').should('be.visible');
        cy.get('@serviceButton').click();
        submenuShouldBeOpened(labelServices);
        cy.matchImageSnapshot();
      });

      it('closes the submenu when clicking outside, using esc key, or clicking again on the button', () => {
        initialize();
        getElements();

        openSubMenu(labelServices);

        cy.get('body').type('{esc}');
        submenuShouldBeClosed(labelServices);

        openSubMenu(labelServices);

        cy.get('body').click();
        submenuShouldBeClosed(labelServices);

        openSubMenu(labelServices);

        cy.get('@serviceButton').click();
        submenuShouldBeClosed(labelServices);
      });

      it('displays the items in the right order, with the right texts and urls', () => {
        const serviceStubs = {
          critical: { total: '2', unhandled: '1' },
          ok: '1',
          pending: '1',
          total: 8,
          unknown: { total: '2', unhandled: '1' },
          warning: { total: '2', unhandled: '1' }
        };

        initialize({ servicesStatus: serviceStubs });
        openSubMenu(labelServices);

        cy.get(`#Services-menu`).within(() => {
          cy.findAllByRole('menuitem').as('items').should('have.length', 6);

          const expectedOrderAndContent = [
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"CRITICAL","name":"Critical"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelCritical
            },
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"WARNING","name":"Warning"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelWarning
            },
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"UNKNOWN","name":"Unknown"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelUnknown
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"OK","name":"Ok"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelOk
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"PENDING","name":"Pending"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelPending
            },
            {
              count: '8',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelAll
            }
          ];

          cy.get('@items').each(($el, index) => {
            cy.wrap($el)
              .should('contain.text', expectedOrderAndContent[index].label)
              .should('contain.text', expectedOrderAndContent[index].count)
              .should('have.attr', 'href', expectedOrderAndContent[index].href);
          });
        });
      });
    });
  });
