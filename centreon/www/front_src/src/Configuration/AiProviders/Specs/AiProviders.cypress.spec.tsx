import {
  labelAiProviders,
  labelAnthropic,
  labelApiToken,
  labelDefault,
  labelEndpointUrl,
  labelMistral,
  labelName
} from '../translatedLabels';
import initialize from './initialize';

describe('AiProviders', () => {
  beforeEach(() => {
    cy.viewport('macbook-15');

    initialize();
  });

  describe('Listing', () => {
    it('displays the AI provider configurations', () => {
      cy.waitForRequest('@getAiProviders');

      cy.contains(labelAiProviders);
      cy.contains('Anthropic production');
      cy.contains('Mistral fallback');
      cy.contains(labelAnthropic);
      cy.contains(labelMistral);
      cy.contains(labelDefault);

      cy.makeSnapshot();
    });

    it('sends a listing request with a search parameter when a name is searched', () => {
      cy.waitForRequest('@getAiProviders');

      cy.findAllByPlaceholderText('Search').clear().type('mistral');

      cy.wait(500);

      cy.waitForRequest('@getAiProviders').then(({ request }) => {
        expect(request.url.href).include('name[lk]=mistral');
      });
    });
  });

  describe('Modal', () => {
    it('prefills the endpoint URL and sends the token when a provider is created', () => {
      cy.waitForRequest('@getAiProviders');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId(labelName).eq(1).type('Anthropic test');

      cy.findByLabelText(labelAnthropic).click();

      cy.findAllByTestId(labelEndpointUrl)
        .eq(1)
        .should('have.value', 'https://api.anthropic.com');

      cy.findAllByTestId(labelApiToken).eq(1).type('a-very-secret-token');

      cy.contains(/^Save$/).click();

      cy.waitForRequest('@createAiProvider').then(({ request }) => {
        expect(request.body.name).to.equal('Anthropic test');
        expect(request.body.type).to.equal('anthropic');
        expect(request.body.api_token).to.equal('a-very-secret-token');
        expect(request.body.endpoint_url).to.equal('https://api.anthropic.com');
      });
    });

    it('keeps the token untouched when a provider is edited without typing a new token', () => {
      cy.waitForRequest('@getAiProviders');

      cy.contains('Anthropic production').click();

      cy.waitForRequest('@getAiProvider');

      cy.findAllByTestId(labelName)
        .eq(1)
        .should('have.value', 'Anthropic production');

      cy.findAllByTestId(labelApiToken).eq(1).should('have.value', '');

      cy.findAllByTestId(labelName).eq(1).clear().type('Anthropic renamed');

      cy.contains(/^Save$/).click();

      cy.waitForRequest('@updateAiProvider').then(({ request }) => {
        expect(request.body.name).to.equal('Anthropic renamed');
        expect(request.body).to.not.have.property('api_token');
      });
    });
  });
});
