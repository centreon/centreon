import {
  labelClear,
  labelDiscovery,
  labelEnabled,
  labelName,
  labelNotification,
  labelSearch,
  labelStatus,
  labelType
} from '../translatedLabels';

import initialize from './initialize';

export default (): void => {
  describe('Filters', () => {
    beforeEach(initialize);
    it('displays the search bar component', () => {
      cy.waitForRequest('@getCommands');

      cy.get('[data-testid="search-bar"]').should('be.visible');

      cy.matchImageSnapshot();
    });
    it('displays the advanced filters component when the corresponding icon is clicked', () => {
      cy.waitForRequest('@getCommands');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get('[data-testid="advanced-filters"]').should('be.visible');

      cy.get(`input[data-testid="${labelName}"`).should('be.visible');
      cy.contains(labelStatus).should('be.visible');
      cy.contains(labelType).should('be.visible');

      cy.get(`button[data-testid="${labelSearch}"`).should('be.visible');
      cy.get(`button[data-testid="${labelClear}"`).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('updates the name filter with the value from the search bar', () => {
      cy.waitForRequest('@getCommands');

      cy.findAllByPlaceholderText(labelSearch).clear().type('abc');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).should('have.value', 'abc');
    });
    it('updates the search bar with the value from the filters', () => {
      cy.waitForRequest('@getCommands');

      cy.findAllByPlaceholderText(labelSearch).clear();
      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).type('abc');

      cy.findAllByPlaceholderText(labelSearch).should('have.value', 'abc');

      cy.matchImageSnapshot();
    });
    it('sends a listing request with the search bar content when after a delay', () => {
      cy.waitForRequest('@getCommands');

      cy.findAllByPlaceholderText(labelSearch).clear().type('abc');

      cy.wait(500);

      cy.waitForRequest('@getCommands').then(({ request }) => {
        expect(request.url.href).to.include('name[lk]=abc');
      });
    });
    it('sends a listing request with selected filters when the search button is clicked', () => {
      cy.waitForRequest('@getCommands');

      cy.findAllByPlaceholderText(labelSearch).clear();

      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).type('abc');

      cy.contains(labelNotification).click();
      cy.contains(labelDiscovery).click();

      cy.contains(labelEnabled).click();

      cy.findByTestId(labelSearch).click();

      cy.wait(50);

      cy.waitForRequest('@getCommands').then(({ request }) => {
        expect(request.url.href).to.include('name[lk]=abc');
        expect(request.url.href).to.include(
          'type[eq]=Notification&type[eq]=Discovery'
        );
        expect(request.url.href).to.include('is_activated=true');
      });
    });

    it('clears filters and the search bar, and sends a listing request with empty search parameter when the clear button is clicked', () => {
      cy.waitForRequest('@getCommands');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).type('abc');

      cy.contains(labelNotification).click();
      cy.contains(labelDiscovery).click();

      cy.contains(labelEnabled).click();

      cy.findByTestId(labelSearch).click();

      cy.get(`button[data-testid="${labelClear}"`).click();

      cy.findAllByPlaceholderText(labelSearch).should('have.value', '');
      cy.get(`input[data-testid="${labelName}"`).should('have.value', '');
      cy.get('input[name="Notification"]').should('not.be.checked');
      cy.get('input[name="Discovery"]').should('not.be.checked');
      cy.get('input[name="Enabled"]').should('not.be.checked');

      cy.waitForRequest('@getCommands').then(({ request }) => {
        expect(request.url.href).to.not.include('name[lk]=abc');
        expect(request.url.href).to.not.include(
          'type[eq]=Notification&type[eq]=Discovery'
        );
        expect(request.url.href).to.not.include('is_activated=true');
      });

      cy.matchImageSnapshot();
    });
  });
};
