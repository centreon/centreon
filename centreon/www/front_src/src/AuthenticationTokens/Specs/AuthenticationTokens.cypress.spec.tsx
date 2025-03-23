import {
  labelAdd,
  labelCreateAuthenticationToken,
  labelDeleteToken,
  labelDisableToken,
  labelDisabled,
  labelDone,
  labelEnableToken,
  labelFilters,
  labelName,
  labelRefresh,
  labelSearch,
  labelSecurityToken,
  labelTokenDeleted,
  labelTokenDisabled,
  labelTokenEnabled,
  labelType,
  labelUser
} from '../translatedLabels';
import { initilize } from './initialize';

describe('Authentication tokens', () => {
  beforeEach(() => {
    initilize();
  });

  it('renders the authentication token page and checks for the presence of all elements', () => {
    cy.waitForRequest('@listToken');

    cy.contains('a-token');

    cy.contains('API');

    cy.makeSnapshot();
  });

  it('refreshes the page when the refresh button is clicked', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId(labelRefresh).click();
    cy.waitForRequest('@listToken');

    cy.makeSnapshot();
  });

  it('searches for tokens using the search bar and verifies the API request', () => {
    cy.waitForRequest('@listToken');

    cy.findAllByPlaceholderText(labelSearch).clear().type('token');

    cy.wait(500);

    cy.waitForRequest('@listToken').then(({ request }) => {
      expect(JSON.parse(request.url.searchParams.get('search'))).to.deep.equal({
        $and: [{ $or: [{ token_name: { $rg: 'token' } }] }, { $or: [] }]
      });
    });

    cy.makeSnapshot();
  });

  it('applies filters and verifies the API request', () => {
    cy.waitForRequest('@listToken');

    cy.get(`[data-testid="${labelFilters}"]`).click();

    cy.get(`[data-testid="${labelName}"]`).eq(1).clear().type('token 1');

    cy.findByTestId(labelDisabled).click();

    cy.findByTestId(labelSearch).click();

    cy.waitForRequest('@listToken').then(({ request }) => {
      expect(JSON.parse(request.url.searchParams.get('search'))).to.deep.equal({
        $and: [
          { $or: [{ token_name: { $rg: 'token 1' } }] },
          { $or: [{ is_revoked: { $eq: true } }] }
        ]
      });
    });

    cy.makeSnapshot();
  });

  it('deletes a token and verifies the deletion request', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId('Delete_d-token_23').click();

    cy.contains(labelDeleteToken);

    cy.findByTestId('confirm').click();

    cy.waitForRequest('@deleteToken');
    cy.waitForRequest('@listToken');

    cy.contains(labelTokenDeleted);

    cy.makeSnapshot();
  });

  it('disables a token and verifies the action', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId('Enable/Disable_d-token_23').click();

    cy.contains(labelDisableToken);

    cy.findByTestId('confirm').click();

    cy.waitForRequest('@enableDisableToken').then(({ request }) => {
      expect(request.body).to.deep.equals({
        is_revoked: true
      });
    });

    cy.waitForRequest('@listToken');

    cy.contains(labelTokenDisabled);

    cy.makeSnapshot();
  });

  it('enables a token and verifies the action', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId('Enable/Disable_e-token_23').click();

    cy.contains(labelEnableToken);

    cy.findByTestId('confirm').click();

    cy.waitForRequest('@enableDisableToken').then(({ request }) => {
      expect(request.body).to.deep.equals({
        is_revoked: false
      });
    });

    cy.waitForRequest('@listToken');

    cy.contains(labelTokenEnabled);

    cy.makeSnapshot();
  });

  it('copies a CMA token to the clipboard and verifies the action', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId('Copy_e-token_23').click();

    cy.waitForRequest('@tokenDetails');

    cy.makeSnapshot();
  });

  it('adds a new API token and verifies the form submission', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId(labelAdd).click();

    cy.contains(labelCreateAuthenticationToken);

    cy.findByTestId('submit').should('be.disabled');

    cy.findAllByTestId(labelName).eq(1).type('token 1');
    cy.findByTestId(labelUser).click();

    cy.contains('admin admin').click();

    cy.makeSnapshot('API token form (before)');

    cy.findByTestId('submit').click();

    cy.waitForRequest('@addToken');

    cy.findByTestId('tokenInput').should('be.visible');

    cy.contains(labelSecurityToken);
    cy.makeSnapshot('API token form (after)');

    cy.contains(labelDone).click();

    cy.findByText(labelCreateAuthenticationToken).should('not.exist');
  });

  it('adds a new CMA token and verifies the form submission.', () => {
    cy.waitForRequest('@listToken');

    cy.findByTestId(labelAdd).click();

    cy.contains(labelCreateAuthenticationToken);

    cy.findByTestId('submit').should('be.disabled');

    cy.findAllByTestId(labelName).eq(1).type('token 1');

    cy.findByTestId(labelType).click();
    cy.contains('Centreon monitoring agent').click();

    cy.findByTestId(labelUser).should('not.exist');

    cy.makeSnapshot('CMA token form (before)');

    cy.findByTestId('submit').click();

    cy.waitForRequest('@addToken');

    cy.findByTestId('tokenInput').should('be.visible');

    cy.findByText(labelSecurityToken).should('not.exist');

    cy.makeSnapshot('CMA token form (after)');

    cy.contains(labelDone).click();

    cy.findByText(labelCreateAuthenticationToken).should('not.exist');
  });
});
