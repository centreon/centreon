import {
  labelDownStatusHosts,
  labelUnreachableStatusHosts,
  labelUpStatusHosts,
  labelHosts,
  labelDown,
  labelAll,
  labelUnreachable,
  labelPending,
  labelUp
} from '../Resources/Host/translatedLabels';

import {
  initialize,
  submenuShouldBeClosed,
  submenuShouldBeOpened,
  openSubMenu
} from './Header.testUtils';

const getElements = (): void => {
  cy.findByRole('button', { name: labelHosts, timeout: 5000 }).as(
    'serviceButton'
  );

  cy.findByRole('link', { name: labelDownStatusHosts }).as('downCounter');

  cy.findByRole('link', { name: labelUnreachableStatusHosts }).as(
    'unreachableCounter'
  );

  cy.findByRole('link', { name: labelUpStatusHosts }).as('upCounter');
};

export default (): void =>
  describe(labelHosts, () => {
    describe('responsive behaviors', () => {
      it('hides the button text when the screen is under 1024px width', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@serviceButton').within(() => {
          cy.findByText(labelHosts).should('not.be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('DnsIcon').should('be.visible');
        });
      });

      it('hides top counters when the screen is is under 600px width', () => {
        initialize();
        getElements();

        cy.viewport(599, 300);
        cy.get('@downCounter').should('not.be.visible');
        cy.get('@unreachableCounter').should('not.be.visible');
        cy.get('@upCounter').should('not.be.visible');
      });
    });

    describe('pending indicator', () => {
      it('displays a pending indicator when the pending count is greater than 0', () => {
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

      it('hides the pending indicator when there is no pending resource', () => {
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

    describe('Status counter', () => {
      it('displays formatted status counter numbers', () => {
        const hoststubs = {
          down: { unhandled: '12' },
          ok: '12134',
          unreachable: { unhandled: '126' }
        };

        initialize({ hosts_status: hoststubs });
        getElements();

        cy.get('@downCounter').should('be.visible').contains('12');
        cy.get('@unreachableCounter').should('be.visible').contains('126');
        cy.get('@upCounter').should('be.visible').contains('12.1k');

        cy.matchImageSnapshot();
      });

      it('redirect to Resources Status with the correct filter when a counter is clicked', () => {
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

    describe('Submenu', () => {
      it('opens the submenu when clicking on the button', () => {
        initialize();
        getElements();
        submenuShouldBeClosed(labelHosts);
        cy.get('@serviceButton').should('be.visible');
        cy.get('@serviceButton').click();
        submenuShouldBeOpened(labelHosts);
        cy.matchImageSnapshot();
      });

      it('closes the submenu when clicking outside, using esc key, or clicking again on the button', () => {
        initialize();
        getElements();

        openSubMenu(labelHosts);

        cy.get('body').type('{esc}');
        submenuShouldBeClosed(labelHosts);

        openSubMenu(labelHosts);

        cy.get('body').click();
        submenuShouldBeClosed(labelHosts);

        openSubMenu(labelHosts);

        cy.get('@serviceButton').click();
        submenuShouldBeClosed(labelHosts);
      });

      it('links to the expected urls', () => {
        const hoststubs = {
          down: { total: '2', unhandled: '1' },
          ok: '1',
          pending: '1',
          total: 8,
          unreachable: { total: '2', unhandled: '1' }
        };

        initialize({ hosts_status: hoststubs });
        openSubMenu(labelHosts);

        cy.get(`#Hosts-menu`).within(() => {
          const expectedOrderAndContent = [
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"DOWN","name":"Down"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelDown
            },
            {
              count: '1/2',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"UNREACHABLE","name":"Unreachable"}]},{"name":"states","value":[{"id":"unhandled_problems","name":"Unhandled"}]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelUnreachable
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"UP","name":"Up"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelUp
            },
            {
              count: '1',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[{"id":"PENDING","name":"Pending"}]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelPending
            },
            {
              count: '8',
              href: '/monitoring/resources?filter={"criterias":[{"name":"resource_types","value":[{"id":"host","name":"Host"}]},{"name":"statuses","value":[]},{"name":"states","value":[]},{"name":"search","value":""}]}&fromTopCounter=true',
              label: labelAll
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
