import {
  labelClear,
  labelFilters,
  labelSearch
} from '../../ConfigurationBase/translatedLabels';
import {
  labelHostGroup,
  labelHostTemplate,
  labelMonitoringServer,
  labelName,
  labelStatus
} from '../translatedLabels';
import initialize from './initialize';

const openAdvancedFilters = (): void => {
  cy.get(`[data-testid="${labelFilters}"]`).click();
  cy.get('[data-testid="advanced-filters"]').should('be.visible');
};

// Scoped to the popper: a template name also appears in the Templates column
// of the listing behind it.
const select = (label: string, option: string, alias: string): void => {
  cy.findByTestId(label).click();
  cy.waitForRequest(alias);
  cy.get('.MuiAutocomplete-popper').contains(option).click();
};

export default () => {
  describe('Filters: ', () => {
    it('offers the five filters of the listing', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      [
        labelName,
        labelHostGroup,
        labelHostTemplate,
        labelMonitoringServer,
        labelStatus
        // Scoped to the panel: "Monitoring server" is also a column header,
        // which sits behind it.
      ].forEach((label) => {
        cy.get('[data-testid="advanced-filters"]')
          .contains(label)
          .should('be.visible');
      });

      cy.makeSnapshot();
    });

    it('sends the host group id as a query parameter', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      select(labelHostGroup, 'Linux servers', '@getHostGroups');

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.searchParams.get('group_id')).to.equal('1');
      });
    });

    it('sends the host template id as a query parameter', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      select(labelHostTemplate, 'generic-active-host', '@getHostTemplates');

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.searchParams.get('template_id')).to.equal('5');
      });
    });

    it('sends the monitoring server id as a query parameter', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      select(labelMonitoringServer, 'Poller EU', '@getPollers');

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.searchParams.get('poller_id')).to.equal('2');
      });
    });

    it('sends the status as `activated`, the parameter this endpoint takes', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      cy.contains(labelStatus).parent().find('input').first().click();

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.searchParams.get('activated')).to.equal('true');
        expect(request.url.searchParams.get('is_activated')).to.equal(null);
      });
    });

    it('combines the filters, then drops them all with the clear action', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      select(labelHostGroup, 'Linux servers', '@getHostGroups');
      select(labelHostTemplate, 'generic-active-host', '@getHostTemplates');

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.searchParams.get('group_id')).to.equal('1');
        expect(request.url.searchParams.get('template_id')).to.equal('5');
      });

      cy.findByTestId(labelClear).click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.searchParams.get('group_id')).to.equal(null);
        expect(request.url.searchParams.get('template_id')).to.equal(null);
      });
    });

    it('keeps the filters in local storage so they survive navigation', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      openAdvancedFilters();

      select(labelHostGroup, 'Linux servers', '@getHostGroups');

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAllHosts');

      cy.window().then((window) => {
        const stored = JSON.parse(
          window.localStorage.getItem('filters_hosts') as string
        );

        expect(stored.group_id).to.deep.equal({ id: 1, name: 'Linux servers' });
      });
    });
  });
};
