import {
  labelClear,
  labelName,
  labelPollers,
  labelSearch,
  labelTypes
} from '../translatedLabels';

import initialize from './initialize';

export default (): void => {
  describe('Filters', () => {
    beforeEach(initialize);
    it('displays the search bar component', () => {
      cy.waitForRequest('@getConnectors');

      cy.get('[data-testid="search-bar"]').should('be.visible');

      cy.matchImageSnapshot();
    });
    it('displays the advanced filters component when the corresponding icon is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get('[data-testid="advanced-filters"]').should('be.visible');

      cy.get(`input[data-testid="${labelName}"`).should('be.visible');
      cy.findByTestId(labelPollers).should('be.visible');
      cy.findByTestId(labelTypes).should('be.visible');

      cy.get(`button[data-testid="${labelSearch}"`).should('be.visible');
      cy.get(`button[data-testid="${labelClear}"`).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('updates the name filter with the value from the search bar', () => {
      cy.waitForRequest('@getConnectors');

      cy.findAllByPlaceholderText(labelSearch).clear().type('vmware1');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).should(
        'have.value',
        'vmware1'
      );
    });
    it('updates the search bar with the value from the filters', () => {
      cy.waitForRequest('@getConnectors');

      cy.findAllByPlaceholderText(labelSearch).clear();
      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findAllByPlaceholderText(labelSearch).should('have.value', 'vmware1');

      cy.matchImageSnapshot();
    });
    it('sends a listing request with the search bar content when after a delay', () => {
      cy.waitForRequest('@getConnectors');

      cy.findAllByPlaceholderText(labelSearch).clear().type('vmware1');

      cy.wait(500);

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({ $and: [{ $or: [{ name: { $rg: 'vmware1' } }] }] });
      });
    });
    it('sends a listing request with selected filters when the search button is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.findAllByPlaceholderText(labelSearch).clear();

      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findByTestId(labelTypes).click();
      cy.findAllByText('VMWare 6/7').eq(10).click();
      cy.findByTestId(labelTypes).click();

      cy.findByTestId(labelPollers).click();
      cy.contains('poller1').click();
      cy.contains('poller2').click();
      cy.findByTestId(labelPollers).click();

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: [
            { $or: [{ name: { $rg: 'vmware1' } }] },
            { $or: [{ type: { $in: ['vmware_v6'] } }] },
            {
              $or: [{ 'poller.id': { $in: [1, 2] } }]
            }
          ]
        });
      });
    });

    it('clears filters and the search bar, and sends a listing request with empty search parameter when the clear button is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findByTestId(labelTypes).click();
      cy.findAllByText('VMWare 6/7').eq(10).click();
      cy.findByTestId(labelTypes).click();

      cy.findByTestId(labelPollers).click();
      cy.contains('poller1').click();
      cy.contains('poller2').click();
      cy.findByTestId(labelPollers).click();

      cy.get(`button[data-testid="${labelClear}"`).click();

      cy.findAllByPlaceholderText(labelSearch).should('have.value', '');

      cy.get(`input[data-testid="${labelName}"`).should('have.value', '');

      cy.findByTestId(labelTypes)
        .parent()
        .within(() => {
          cy.findByText('VMWare_6/7').should('not.exist');
        });

      cy.findByTestId(labelPollers)
        .parent()
        .within(() => {
          cy.findByText('poller1').should('not.exist');
          cy.findByText('poller2').should('not.exist');
        });

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({ $and: [] });
      });

      cy.matchImageSnapshot();
    });
  });
};
