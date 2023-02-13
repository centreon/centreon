import {
  downStatusHosts,
  unreachableStatusHosts,
  upStatusHosts
} from '../Resources/Host/translatedLabels';

import { initialize } from './Header.testUtils';

const getElements = () => {
  cy.findByRole('button', { name: 'Hosts', timeout: 5000 }).as('serviceButton');

  cy.findByRole('link', { name: downStatusHosts }).as('downCounter');

  cy.findByRole('link', { name: unreachableStatusHosts }).as(
    'unreachableCounter'
  );

  cy.findByRole('link', { name: upStatusHosts }).as('upCounter');
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
  describe('Hosts', () => {
    describe('responsive behaviors', () => {
      it('should hide button text at smaller screen size', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText('Hosts').should('be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('DnsIcon').should('be.visible');
        });

        cy.viewport(767, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText('Hosts').should('not.be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('DnsIcon').should('be.visible');
        });
      });

      it('should hide top counters at very small size', () => {
        initialize();
        getElements();

        cy.viewport(599, 300);
        cy.get('@downCounter').should('not.be.visible');
        cy.get('@unreachableCounter').should('not.be.visible');
        cy.get('@upCounter').should('not.be.visible');
      });
    });

    describe('pending indicator', () => {
      it('should have a pending indicator if pending > 0', () => {
        const hoststubs = {
          pending: '1'
        };

        initialize({ hosts_status: hoststubs });
        getElements();

        cy.get('@serviceButton').within(() => {
          cy.get('.MuiBadge-badge.MuiBadge-colorPending')
            .should('exist')
            .should('be.visible');
        });
      });

      it('should hide the pending indicator there is no pending ressources', () => {
        const hoststubs = {
          pending: '0'
        };

        initialize({ hosts_status: hoststubs });
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
        const hoststubs = {
          down: { unhandled: '12' },
          ok: '12134',
          unreachable: { unhandled: '126' }
        };

        initialize({ hosts_status: hoststubs });
        getElements();

        // 12 => 12
        cy.get('@downCounter').should('be.visible').contains('12');

        // 125 => 125
        cy.get('@unreachableCounter').should('be.visible').contains('126');

        // 12134 => 12.1k
        cy.get('@upCounter').should('be.visible').contains('12.1k');

        cy.matchImageSnapshot();
      });

      it('should redirect on click on the counter', () => {
        // given
        const hoststubs = {
          critical: { unhandled: '12' },
          ok: '12134',
          unknown: { unhandled: '125' },
          warning: { unhandled: '14688222' }
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
      });
    });

    describe('sub menu', () => {
      it('should have a button to open the submenu', () => {
        initialize();
        getElements();
        submenuShouldBeClosed('Hosts');
        cy.get('@serviceButton').should('be.visible');
        cy.get('@serviceButton').click();
        submenuShouldBeOpened('Hosts');
        cy.matchImageSnapshot();
      });

      it('should be able to close the submenu by clicking outside, using esc key, or clicking again on the button', () => {
        initialize();
        getElements();

        openSubMenu('Hosts');

        cy.get('body').type('{esc}');
        submenuShouldBeClosed('Hosts');

        openSubMenu('Hosts');

        cy.get('body').click();
        submenuShouldBeClosed('Hosts');

        openSubMenu('Hosts');

        cy.get('@serviceButton').click();
        submenuShouldBeClosed('Hosts');
      });

      it('should have all the required items links', () => {
        const hoststubs = {
          down: { total: '2', unhandled: '1' },
          ok: '1',
          pending: '1',
          total: 8,
          unreachable: { total: '2', unhandled: '1' }
        };

        initialize({ hosts_status: hoststubs });
        openSubMenu('Hosts');

        cy.get(`#Hosts-menu`).within(() => {
          const expectedOrderAndContent = [
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"DOWN","name":"Down"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Down'
            },
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"UNREACHABLE","name":"Unreachable"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Unreachable'
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"UP","name":"Up"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Up'
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"PENDING","name":"Pending"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'Pending'
            },
            {
              count: '8',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: 'All'
            }
          ];

          cy.findAllByRole('menuitem')
            .as('items')
            .should('have.length', expectedOrderAndContent.length);

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
