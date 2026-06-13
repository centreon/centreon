import {
  labelAiProviders,
  labelAnthropic,
  labelApiToken,
  labelCurrency,
  labelDailyLimit,
  labelDefaultModel,
  labelDefaultModelInputPrice,
  labelDefaultModelOutputPrice,
  labelEndpointUrl,
  labelFastModel,
  labelMistral,
  labelName,
  labelNameAlreadyExists,
  labelOpenAiCompatible
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

      // the enabled/disabled switches reflect the providers state
      cy.findByTestId('Enable/Disable_1').find('input').should('be.checked');
      cy.findByTestId('Enable/Disable_2')
        .find('input')
        .should('not.be.checked');

      cy.makeSnapshot();
    });

    it('sends an activation request when the switch of a disabled provider is clicked', () => {
      cy.waitForRequest('@getAiProviders');

      cy.findByTestId('Enable/Disable_2').click();

      cy.waitForRequest('@enableAiProvider').then(({ request }) => {
        expect(request.body).to.deep.equal({ is_activated: true });
      });
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
    it('hides the endpoint URL for curated providers and sends the token when a provider is created', () => {
      cy.waitForRequest('@getAiProviders');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId(labelName).eq(1).type('Anthropic test');

      cy.findByLabelText(labelAnthropic).click();

      // the endpoint of curated providers is enforced by the API
      cy.get('[data-testid="Modal"]').within(() => {
        cy.findAllByTestId(labelEndpointUrl).should('have.length', 0);
      });

      // the models are prefilled for curated providers
      cy.findAllByTestId(labelDefaultModel)
        .eq(1)
        .should('have.value', 'claude-sonnet-4-6');

      cy.findAllByTestId(labelFastModel)
        .eq(1)
        .should('have.value', 'claude-haiku-4-5');

      cy.findAllByTestId(labelApiToken).eq(1).type('a-very-secret-token');

      cy.contains(/^Save$/).click();

      cy.waitForRequest('@createAiProvider').then(({ request }) => {
        expect(request.body.name).to.equal('Anthropic test');
        expect(request.body.type).to.equal('anthropic');
        expect(request.body.api_token).to.equal('a-very-secret-token');
        expect(request.body).to.not.have.property('endpoint_url');
        expect(request.body.default_model).to.equal('claude-sonnet-4-6');
        expect(request.body.fast_model).to.equal('claude-haiku-4-5');
        // the first provider is enabled by the API regardless of the form switch
        expect(request.body.is_activated).to.equal(false);
      });
    });

    it('sends the currency, prices and limit when they are filled', () => {
      cy.waitForRequest('@getAiProviders');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByLabelText(labelAnthropic).click();
      cy.findAllByTestId(labelName).eq(1).clear().type('Anthropic priced');
      cy.findAllByTestId(labelApiToken).eq(1).type('a-very-secret-token');

      cy.findAllByTestId(labelCurrency).eq(1).type('USD');
      cy.findAllByTestId(labelDefaultModelInputPrice).eq(1).type('3');
      cy.findAllByTestId(labelDefaultModelOutputPrice).eq(1).type('15');
      cy.findAllByTestId(labelDailyLimit).eq(1).type('10');

      cy.contains(/^Save$/).click();

      cy.waitForRequest('@createAiProvider').then(({ request }) => {
        expect(request.body.currency).to.equal('USD');
        expect(request.body.price_default_input).to.equal(3);
        expect(request.body.price_default_output).to.equal(15);
        expect(request.body.limit_daily).to.equal(10);
        expect(request.body.limit_monthly).to.equal(null);
      });
    });

    it('defaults the name to the provider type until the user chooses one', () => {
      cy.waitForRequest('@getAiProviders');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByLabelText(labelMistral).click();

      cy.findAllByTestId(labelName).eq(1).should('have.value', 'Mistral');

      cy.findAllByTestId(labelDefaultModel)
        .eq(1)
        .should('have.value', 'mistral-medium-3-5');

      // the name and models follow the type while still auto-filled
      cy.findByLabelText(labelAnthropic).click();

      cy.findAllByTestId(labelName).eq(1).should('have.value', 'Anthropic');

      cy.findAllByTestId(labelDefaultModel)
        .eq(1)
        .should('have.value', 'claude-sonnet-4-6');

      // a name chosen by the user is left untouched
      cy.findAllByTestId(labelName).eq(1).clear().type('My provider');

      cy.findByLabelText(labelMistral).click();

      cy.findAllByTestId(labelName).eq(1).should('have.value', 'My provider');
    });

    it('warns and disables saving when the name is already used', () => {
      cy.waitForRequest('@getAiProviders');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByLabelText(labelAnthropic).click();

      cy.findAllByTestId(labelName).eq(1).clear().type('Anthropic production');

      cy.findAllByTestId(labelApiToken).eq(1).type('a-very-secret-token');

      cy.contains(labelNameAlreadyExists);

      cy.contains(/^Save$/).should('be.disabled');

      cy.makeSnapshot();
    });

    it('displays the endpoint URL above the token for OpenAI-compatible providers', () => {
      cy.waitForRequest('@getAiProviders');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByLabelText(labelOpenAiCompatible).click();

      const isFollowing = (first: Element, second: Element): boolean =>
        (first.compareDocumentPosition(second) &
          Node.DOCUMENT_POSITION_FOLLOWING) !==
        0;

      cy.get('[data-testid="Modal"]').within(() => {
        cy.findAllByTestId(labelEndpointUrl)
          .last()
          .type('https://llm-gateway.example.com');

        cy.findAllByTestId(labelEndpointUrl).then(($endpoint) => {
          cy.findAllByTestId(labelApiToken).then(($token) => {
            cy.findAllByTestId(labelDefaultModel).then(($model) => {
              expect(isFollowing($endpoint[0], $token[0])).to.equal(true);
              expect(isFollowing($token[0], $model[0])).to.equal(true);
            });
          });
        });
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
