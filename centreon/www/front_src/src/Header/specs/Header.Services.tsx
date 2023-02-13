import {
  criticalStatusServices,
  warningStatusServices,
  unknownStatusServices,
  okStatusServices
} from '../Resources/Service/translatedLabels';

import { initialize } from './Header.testUtils';

const getElements = () => {
  cy.findByRole('button', { name: 'Services', timeout: 5000 }).as(
    'serviceButton'
  );

  cy.findByRole('link', { name: criticalStatusServices }).as('criticalCounter');

  cy.findByRole('link', { name: unknownStatusServices }).as('unknownCounter');

  cy.findByRole('link', { name: okStatusServices }).as('okCounter');

  cy.findByRole('link', { name: warningStatusServices }).as('warningCounter');
};

const submenuShouldBeClosed = (label) => {
  cy.findByRole('button', { name: label })
    .as('button')
    .should('have.attr', 'aria-expanded', 'false');

  cy.get('@button').within(() => {
    cy.findByTestId('ExpandLessIcon').should('be.visible');
  });
  cy.get(`#${label}-menu`).should('not.be.visible').should('exist');
};

const openSubMenu = (label) => {
  cy.findByRole('button', {
    name: label
  }).click();
  submenuShouldBeOpened(label);
};

const submenuShouldBeOpened = (label) => {
  cy.findByRole('button', { name: label })
    .as('button')
    .should('have.attr', 'aria-expanded', 'true');

  cy.get('@button').within(() => {
    cy.findByTestId('ExpandMoreIcon').should('be.visible');
  });
  cy.get(`#${label}-menu`).should('be.visible').should('exist');
};

export default (): void =>
  describe('Services', () => {
    describe('responsive behaviors', () => {
      it('should hide button text at smaller screen size', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText('Services').should('be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('GrainIcon').should('be.visible');
        });

        cy.viewport(767, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText('Services').should('not.be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('GrainIcon').should('be.visible');
        });
      });

      it('should hide top counters at very small size', () => {
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
      it('should have a pending indicator if pending > 0', () => {
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

      it('should hide the pending indicator there is no pending ressources', () => {
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
      it('should display status counter numbers with the desired format', () => {
        // given
        const serviceStubs = {
          critical: { unhandled: '12' },
          ok: '12134',
          unknown: { unhandled: '126' },
          warning: { unhandled: '14688222' }
        };

        initialize({ servicesStatus: serviceStubs });
        getElements();

        // 12 => 12
        cy.get('@criticalCounter').should('be.visible').contains('12');

        // 125 => 125
        cy.get('@unknownCounter').should('be.visible').contains('126');

        // 12134 => 12.1k
        cy.get('@okCounter').should('be.visible').contains('12.1k');

        // 14688222 => 14.7m
        cy.get('@warningCounter').should('be.visible').contains('14.7m');

        cy.matchImageSnapshot();
      });

      it('should redirect on click on the counter', () => {
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
      it('should have a button to open the submenu', () => {
        initialize();
        getElements();
        submenuShouldBeClosed('Services');
        cy.get('@serviceButton').should('be.visible');
        cy.get('@serviceButton').click();
        submenuShouldBeOpened('Services');
        cy.matchImageSnapshot();
      });

      it('should be able to close the submenu by clicking outside, using esc key, or clicking again on the button', () => {
        initialize();
        getElements();

        openSubMenu('Services');

        cy.get('body').type('{esc}');
        submenuShouldBeClosed('Services');

        openSubMenu('Services');

        cy.get('body').click();
        submenuShouldBeClosed('Services');

        openSubMenu('Services');

        cy.get('@serviceButton').click();
        submenuShouldBeClosed('Services');
      });

      it('should have all the required items links', () => {
        const serviceStubs = {
          critical: { total: '2', unhandled: '1' },
          ok: '1',
          pending: '1',
          total: 8,
          unknown: { total: '2', unhandled: '1' },
          warning: { total: '2', unhandled: '1' }
        };

        initialize({ servicesStatus: serviceStubs });
        openSubMenu('Services');

        cy.get(`#Services-menu`).within(() => {
          cy.findAllByRole('menuitem').as('items').should('have.length', 6);

          const expectedOrderAndContent = [
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"CRITICAL","name":"Critical"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Critical'
            },
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"WARNING","name":"Warning"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Warning'
            },
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"UNKNOWN","name":"Unknown"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Unknown'
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"OK","name":"Ok"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Ok'
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[{"id":"PENDING","name":"Pending"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Pending'
            },
            {
              count: '8',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"service","name":"Service"}]},{"name":"statuses","value":[]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'All'
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
