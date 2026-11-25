import {
  getRequestCalls,
  interceptAPIRequest,
  interceptRequest,
  Method,
  waitForRequest
} from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';

import { labelClose } from '../../ConfigurationBase/translatedLabels';
import {
  aiProvidersEndpoint,
  discoverModelsEndpoint,
  getAiProviderEndpoint,
  modelPricesEndpoint,
  testConnectionEndpoint
} from '../api';
import {
  labelAnthropic,
  labelApiToken,
  labelApiTokenRequiredForNewEndpoint,
  labelAwsRegion,
  labelBedrock,
  labelConnectionSucceededWithModel,
  labelConnectionSucceededWithModelAndReasoning,
  labelConnectionSucceededWithReasoning,
  labelConnectionWarningEmptyAnswer,
  labelConnectionWarningNoReasoningSetting,
  labelConnectionWarningRefused,
  labelConnectionWarningUnknown,
  labelCurrency,
  labelCurrencyChangeResetsUsage,
  labelCurrencyRequired,
  labelDailyLimit,
  labelDefaultModel,
  labelDefaultModelInputPrice,
  labelDefaultModelOutputPrice,
  labelFastModel,
  labelFetchModels,
  labelFetchPrices,
  labelGoogle,
  labelMistral,
  labelNameAlreadyExists,
  labelOpenAiCompatible,
  labelPricesFilled,
  labelPricingRequiredForLimit,
  labelReasoningModelDefault,
  labelTestConnection,
  labelUnreadableAnswer
} from '../translatedLabels';
import {
  defaultReasoningTestId,
  endpointUrlTestId,
  fastReasoningTestId,
  getApiToken,
  getCustomInput,
  getName,
  getSaveButton,
  initializeListing,
  isActivatedTestId,
  openCreationForm,
  openProvider,
  queryAllInPanel,
  replaceValue,
  save,
  selectReasoning,
  selectType
} from './helpers';
import {
  connectionTestResponse,
  discoveredModelsResponse,
  getAiProviderResponse,
  getProvidersCountResponse,
  priceListUnavailableMessage
} from './utils';

interface Payload {
  [key: string]: unknown;
}

const expectValue = (testId: string, value: string): Promise<void> =>
  waitFor(async () => expect(await getCustomInput(testId)).toHaveValue(value));

const getEnabledSwitch = async (): Promise<HTMLElement> =>
  within(await screen.findByTestId(isActivatedTestId)).getByRole('checkbox');

// a provider with prices, metered in USD
const pricedProvider = {
  ...getAiProviderResponse(),
  currency: 'USD',
  price_default_input: 3,
  price_default_output: 15
};

// Holds the price lookup answer until the test releases it.
const interceptSlowPrices = (): (() => void) => {
  let release = (): void => undefined;
  const released = new Promise<void>((resolve) => {
    release = resolve;
  });

  interceptRequest(
    Method.POST,
    `**${modelPricesEndpoint}`,
    async () => {
      await released;

      return HttpResponse.json({
        default_model_input: 3,
        default_model_output: 15
      });
    },
    'slowPrices'
  );

  return release;
};

const isFollowing = (first: Element, second: Element): boolean =>
  (first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING) !==
  0;

export default (): void => {
  describe('Form panel', () => {
    it('hides the endpoint URL for curated providers and sends the token when a provider is created', async () => {
      await openCreationForm();

      await userEvent.type(await getName(), 'Anthropic test');

      await selectType(labelAnthropic);

      // the endpoint of curated providers is enforced by the API
      expect(await queryAllInPanel(endpointUrlTestId)).toHaveLength(0);

      // the models are prefilled for curated providers
      await expectValue(labelDefaultModel, 'claude-sonnet-5-5');
      await expectValue(labelFastModel, 'claude-haiku-5-5');

      // both models reason as little as they allow by default
      await expectValue(defaultReasoningTestId, 'minimal');
      await expectValue(fastReasoningTestId, 'minimal');

      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      await save();

      const { request } = await waitForRequest('@createAiProvider');
      const body = request.body as Payload;

      expect(body.default_model_reasoning).toBe('minimal');
      expect(body.fast_model_reasoning).toBe('minimal');
      expect(body.name).toBe('Anthropic test');
      expect(body.type).toBe('anthropic');
      expect(body.api_token).toBe('a-very-secret-token');
      expect(body).not.toHaveProperty('endpoint_url');
      expect(body.default_model).toBe('claude-sonnet-5-5');
      expect(body.fast_model).toBe('claude-haiku-5-5');
      // the first provider is enabled by the API regardless of the form switch
      expect(body.is_activated).toBe(false);
    });

    it('sends the currency, prices and limit when they are filled', async () => {
      await openCreationForm();

      await selectType(labelAnthropic);
      await replaceValue(await getName(), 'Anthropic priced');
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      await userEvent.type(
        await getCustomInput(labelDefaultModelInputPrice),
        '0.01'
      );
      await userEvent.type(
        await getCustomInput(labelDefaultModelOutputPrice),
        '15'
      );
      await userEvent.type(await getCustomInput(labelDailyLimit), '10');

      // Escape closes the currency dropdown so it does not cover the Save button
      await userEvent.type(await getCustomInput(labelCurrency), 'USD{Escape}');
      await expectValue(labelCurrency, 'USD');

      await save();

      const { request } = await waitForRequest('@createAiProvider');
      const body = request.body as Payload;

      expect(body.currency).toBe('USD');
      // decimal prices are kept
      expect(body.price_default_input).toBe(0.01);
      expect(body.price_default_output).toBe(15);
      expect(body.limit_daily).toBe(10);
      expect(body.limit_monthly).toBe(null);
    });

    it('enables the test-connection button only once a token can be tested', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      // on creation with no token typed yet, there is nothing to test
      expect(await screen.findByTestId(labelTestConnection)).toBeDisabled();

      // a typed token makes the configuration testable
      await userEvent.type(await getApiToken(), 'a-very-secret-token');
      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
    });

    it('offers the LiteLLM price prefill only for a USD or empty currency', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      // a model is prefilled and the currency is empty: prefill is available
      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeEnabled()
      );

      // a non-USD currency would mix units: prefill is disabled
      await userEvent.type(await getCustomInput(labelCurrency), 'EUR');
      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeDisabled()
      );

      await replaceValue(await getCustomInput(labelCurrency), 'USD');
      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeEnabled()
      );
    });

    it('defaults the name to the provider type until the user chooses one', async () => {
      await openCreationForm();

      await selectType(labelMistral);

      await waitFor(async () => expect(await getName()).toHaveValue('Mistral'));
      await expectValue(labelDefaultModel, 'mistral-large-latest');

      // the name and models follow the type while still auto-filled
      await selectType(labelAnthropic);

      await waitFor(async () =>
        expect(await getName()).toHaveValue('Anthropic')
      );
      await expectValue(labelDefaultModel, 'claude-sonnet-5-5');

      // a name chosen by the user is left untouched
      await replaceValue(await getName(), 'My provider');

      await selectType(labelMistral);

      await waitFor(async () =>
        expect(await getName()).toHaveValue('My provider')
      );
    });

    it('warns and disables saving when the name is already used', async () => {
      await openCreationForm();

      await selectType(labelAnthropic);

      await replaceValue(await getName(), 'Anthropic production');

      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      // the name lookup is debounced
      expect(
        await screen.findByText(labelNameAlreadyExists, {}, { timeout: 3000 })
      ).toBeVisible();

      // the form publishes its validity to the panel header after rendering
      await waitFor(() => expect(getSaveButton()).toBeDisabled());
    });

    it('displays the endpoint URL above the token for OpenAI-compatible providers', async () => {
      await openCreationForm();

      await selectType(labelOpenAiCompatible);

      const endpointUrl = (await queryAllInPanel(endpointUrlTestId)).at(
        -1
      ) as HTMLElement;

      await userEvent.type(endpointUrl, 'https://llm-gateway.example.com');

      const [endpoint] = await queryAllInPanel(endpointUrlTestId);
      const [token] = await queryAllInPanel(labelApiToken);
      const [model] = await queryAllInPanel(labelDefaultModel);

      expect(isFollowing(endpoint, token)).toBe(true);
      expect(isFollowing(token, model)).toBe(true);
    });

    it('sends a model discovery request gated on an available token', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      // no token yet: nothing to authenticate the discovery with
      expect(await screen.findByTestId(labelFetchModels)).toBeDisabled();

      await userEvent.type(await getApiToken(), 'a-secret-token');
      await waitFor(() =>
        expect(screen.getByTestId(labelFetchModels)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchModels));

      // the typed token and type are sent to the discovery endpoint
      const { request } = await waitForRequest('@discoverModels');
      const body = request.body as Payload;

      expect(body.type).toBe('anthropic');
      expect(body.api_token).toBe('a-secret-token');
      expect(body).not.toHaveProperty('endpoint_url');
    });

    it('treats Google Gemini as a curated provider with a prefilled model', async () => {
      await openCreationForm();

      await selectType(labelGoogle);

      // Gemini is curated: its endpoint is enforced API-side, not exposed
      expect(await queryAllInPanel(endpointUrlTestId)).toHaveLength(0);
      expect(await queryAllInPanel(labelAwsRegion)).toHaveLength(0);

      await expectValue(labelDefaultModel, 'gemini-4-hydrogen');

      await userEvent.type(await getApiToken(), 'a-gemini-key');

      await save();

      const { request } = await waitForRequest('@createAiProvider');
      const body = request.body as Payload;

      expect(body.type).toBe('google');
      expect(body).not.toHaveProperty('endpoint_url');
      expect(body.default_model).toBe('gemini-4-hydrogen');
    });

    it('derives the Bedrock endpoint from the selected AWS region', async () => {
      await openCreationForm();

      await selectType(labelBedrock);

      // the raw endpoint URL field is replaced by an AWS region selector
      expect(await queryAllInPanel(endpointUrlTestId)).toHaveLength(0);

      // models are prefilled with bare Mantle ids (no region prefix)
      await expectValue(labelDefaultModel, 'qwen.qwen3-235b-a22b-2507');

      await userEvent.type(await getApiToken(), 'a-bedrock-key');
      // Escape closes the dropdown so it does not cover the Save button
      await userEvent.type(
        await getCustomInput(labelAwsRegion),
        'us-east-1{Escape}'
      );

      // the region only drives the endpoint host; the model id is left as is
      await expectValue(labelDefaultModel, 'qwen.qwen3-235b-a22b-2507');

      await save();

      const { request } = await waitForRequest('@createAiProvider');
      const body = request.body as Payload;

      expect(body.type).toBe('bedrock');
      // the endpoint is derived from the region, on the bedrock-mantle host
      expect(body.endpoint_url).toBe(
        'https://bedrock-mantle.us-east-1.api.aws'
      );
      expect(body.default_model).toBe('qwen.qwen3-235b-a22b-2507');
    });

    it('starts the add form enabled only while no provider exists, the count refreshed after a creation', async () => {
      await initializeListing({ providersTotal: 0 });

      await userEvent.click(await screen.findByTestId('add-resource'));
      await waitForRequest('@getAiProvidersCount');

      // the very first provider is enabled
      await waitFor(async () => expect(await getEnabledSwitch()).toBeChecked());

      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      // the API now counts the created provider
      interceptAPIRequest({
        alias: 'getAiProvidersCountAfterCreation',
        method: Method.GET,
        path: `**${aiProvidersEndpoint}?**`,
        query: { name: 'limit', value: '1' },
        response: getProvidersCountResponse(1)
      });

      await save();
      await waitForRequest('@createAiProvider');
      await waitForRequest('@getAiProvidersCountAfterCreation');

      await userEvent.click(await screen.findByTestId('add-resource'));

      // enabling a second provider would disable the first one
      await waitFor(async () =>
        expect(await getEnabledSwitch()).not.toBeChecked()
      );
    });

    it('requires a currency once a price is set', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      await userEvent.type(
        await getCustomInput(labelDefaultModelInputPrice),
        '3'
      );
      await userEvent.click(await getCustomInput(labelCurrency));
      await userEvent.tab();

      expect(await screen.findByText(labelCurrencyRequired)).toBeVisible();
    });

    it('requires the default model prices to set a spend limit', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      await userEvent.type(await getCustomInput(labelDailyLimit), '10');
      await userEvent.tab();

      expect(
        await screen.findByText(labelPricingRequiredForLimit)
      ).toBeVisible();

      await userEvent.type(
        await getCustomInput(labelDefaultModelInputPrice),
        '3'
      );
      await userEvent.type(
        await getCustomInput(labelDefaultModelOutputPrice),
        '15'
      );

      await waitFor(() =>
        expect(screen.queryByText(labelPricingRequiredForLimit)).toBeNull()
      );
    });

    it('warns about the usage totals only when the currency of an existing provider changes', async () => {
      await openProvider({ aiProvider: pricedProvider });

      await expectValue(labelCurrency, 'USD');
      expect(screen.queryByText(labelCurrencyChangeResetsUsage)).toBeNull();

      await replaceValue(await getCustomInput(labelCurrency), 'EUR');

      expect(
        await screen.findByText(labelCurrencyChangeResetsUsage)
      ).toBeVisible();

      await replaceValue(await getCustomInput(labelCurrency), 'USD');

      await waitFor(() =>
        expect(screen.queryByText(labelCurrencyChangeResetsUsage)).toBeNull()
      );
    });

    it('does not warn about the usage totals when a currency is set on a new provider', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      await userEvent.type(await getCustomInput(labelCurrency), 'EUR');
      await expectValue(labelCurrency, 'EUR');

      expect(screen.queryByText(labelCurrencyChangeResetsUsage)).toBeNull();
    });

    it('hides the connection test result once the tested inputs change', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@testConnection');

      const success = labelConnectionSucceededWithModel.replace(
        '{{model}}',
        'claude-sonnet-5-5'
      );

      expect(await screen.findByText(success)).toBeVisible();

      await replaceValue(await getCustomInput(labelDefaultModel), 'other');

      await waitFor(() => expect(screen.queryByText(success)).toBeNull());
    });

    it('sends the reasoning settings chosen for a new provider', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      await selectReasoning(defaultReasoningTestId, labelReasoningModelDefault);
      await selectReasoning(fastReasoningTestId, labelReasoningModelDefault);

      await expectValue(defaultReasoningTestId, 'default');
      await expectValue(fastReasoningTestId, 'default');

      await save();

      const { request } = await waitForRequest('@createAiProvider');
      const body = request.body as Payload;

      expect(body.default_model_reasoning).toBe('default');
      expect(body.fast_model_reasoning).toBe('default');
    });

    it('shows the stored reasoning settings and sends the changed one when a provider is updated', async () => {
      await openProvider({
        aiProvider: {
          ...getAiProviderResponse(),
          default_model_reasoning: 'default',
          fast_model: 'claude-haiku-4-5',
          fast_model_reasoning: 'minimal'
        }
      });

      await expectValue(defaultReasoningTestId, 'default');
      await expectValue(fastReasoningTestId, 'minimal');

      await selectReasoning(fastReasoningTestId, labelReasoningModelDefault);
      await expectValue(fastReasoningTestId, 'default');

      await save();

      const { request } = await waitForRequest('@updateAiProvider');
      const body = request.body as Payload;

      expect(body.default_model_reasoning).toBe('default');
      expect(body.fast_model_reasoning).toBe('default');
    });

    it('offers the fast model reasoning setting only when a fast model is set', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      // the fast model is prefilled for curated providers
      await expectValue(labelFastModel, 'claude-haiku-5-5');
      expect(await queryAllInPanel(fastReasoningTestId)).toHaveLength(1);

      // without a fast model, the default model's setting applies
      await userEvent.clear(await getCustomInput(labelFastModel));

      await waitFor(async () =>
        expect(await queryAllInPanel(fastReasoningTestId)).toHaveLength(0)
      );
      expect(await queryAllInPanel(defaultReasoningTestId)).toHaveLength(1);

      await userEvent.type(await getCustomInput(labelFastModel), 'other');

      await waitFor(async () =>
        expect(await queryAllInPanel(fastReasoningTestId)).toHaveLength(1)
      );
    });

    it('sends the reasoning setting with the connection test and shows the reasoning applied', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      interceptAPIRequest({
        alias: 'testConnectionWithReasoning',
        method: Method.POST,
        path: `**${testConnectionEndpoint}`,
        response: {
          ...connectionTestResponse,
          reasoning: 'reasoning_effort: low'
        }
      });

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));

      const { request } = await waitForRequest('@testConnectionWithReasoning');

      expect((request.body as Payload).default_model_reasoning).toBe('minimal');

      const success = labelConnectionSucceededWithModelAndReasoning
        .replace('{{model}}', 'claude-sonnet-5-5')
        .replace('{{reasoning}}', 'reasoning_effort: low');

      expect(await screen.findByText(success)).toBeVisible();

      // the result only holds for the reasoning setting it was obtained with
      await selectReasoning(defaultReasoningTestId, labelReasoningModelDefault);

      await waitFor(() => expect(screen.queryByText(success)).toBeNull());

      await userEvent.click(screen.getByTestId(labelTestConnection));

      const { request: secondRequest } = await waitForRequest(
        '@testConnectionWithReasoning'
      );

      expect((secondRequest.body as Payload).default_model_reasoning).toBe(
        'default'
      );
    });

    it('notes, under a successful connection test, that the model accepted no setting to reason less', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      interceptAPIRequest({
        alias: 'testConnectionWithWarning',
        method: Method.POST,
        path: `**${testConnectionEndpoint}`,
        response: {
          ...connectionTestResponse,
          warning: 'no_reasoning_setting'
        }
      });

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@testConnectionWithWarning');

      expect(
        await screen.findByText(
          labelConnectionSucceededWithModel.replace(
            '{{model}}',
            'claude-sonnet-5-5'
          )
        )
      ).toBeVisible();
      expect(
        screen.getByText(labelConnectionWarningNoReasoningSetting)
      ).toBeVisible();
      // a note, not a warning: most often a model that does not reason
      const note = screen.getByTestId('connectionTestWarning');

      expect(within(note).getByTestId('InfoOutlinedIcon')).toBeInTheDocument();
      expect(within(note).queryByTestId('WarningIcon')).toBeNull();
    });

    it('warns, under a successful connection test, about an answer the AI features would reject', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      interceptAPIRequest({
        alias: 'testConnectionRefused',
        method: Method.POST,
        path: `**${testConnectionEndpoint}`,
        response: {
          ...connectionTestResponse,
          message: 'the answer was blocked by a content filter.',
          warning: 'refused'
        }
      });

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@testConnectionRefused');

      const warning = await screen.findByTestId('connectionTestWarning');

      expect(warning).toHaveTextContent(
        labelConnectionWarningRefused.replace(
          '{{reason}}',
          'the answer was blocked by a content filter.'
        )
      );
      expect(within(warning).getByTestId('WarningIcon')).toBeInTheDocument();

      interceptAPIRequest({
        alias: 'testConnectionEmptyAnswer',
        method: Method.POST,
        path: `**${testConnectionEndpoint}`,
        response: { ...connectionTestResponse, warning: 'empty_answer' }
      });
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@testConnectionEmptyAnswer');

      expect(
        await screen.findByText(labelConnectionWarningEmptyAnswer)
      ).toBeVisible();
      expect(
        within(screen.getByTestId('connectionTestWarning')).getByTestId(
          'WarningIcon'
        )
      ).toBeInTheDocument();
    });

    it('notes a warning code it does not know, the connection still showing as successful', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      interceptAPIRequest({
        alias: 'testConnectionWithUnknownWarning',
        method: Method.POST,
        path: `**${testConnectionEndpoint}`,
        response: { ...connectionTestResponse, warning: 'something_new' }
      });

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@testConnectionWithUnknownWarning');

      expect(
        await screen.findByText(
          labelConnectionSucceededWithModel.replace(
            '{{model}}',
            'claude-sonnet-5-5'
          )
        )
      ).toBeVisible();
      expect(screen.getByText(labelConnectionWarningUnknown)).toBeVisible();
    });

    it('shows no warning under a plain successful connection test', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));

      expect(
        await screen.findByText(
          labelConnectionSucceededWithModel.replace(
            '{{model}}',
            'claude-sonnet-5-5'
          )
        )
      ).toBeVisible();
      expect(
        screen.queryByTestId('connectionTestWarning')
      ).not.toBeInTheDocument();
    });

    it('shows the reasoning applied when the provider does not name the model that answered', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      interceptAPIRequest({
        alias: 'testConnectionWithoutModel',
        method: Method.POST,
        path: `**${testConnectionEndpoint}`,
        response: {
          ...connectionTestResponse,
          model: null,
          reasoning: 'thinking: disabled'
        }
      });

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@testConnectionWithoutModel');

      expect(
        await screen.findByText(
          labelConnectionSucceededWithReasoning.replace(
            '{{reasoning}}',
            'thinking: disabled'
          )
        )
      ).toBeVisible();
    });

    it('shows why the prices could not be fetched', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchPrices));
      await waitForRequest('@fetchPrices');

      expect(
        await screen.findByText(priceListUnavailableMessage)
      ).toBeVisible();
    });

    it('forgets the discovered models once the form is closed', async () => {
      await openProvider();

      await waitFor(() =>
        expect(screen.getByTestId(labelFetchModels)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchModels));
      await waitForRequest('@discoverModels');

      await userEvent.click(await getCustomInput(labelDefaultModel));
      expect(
        await screen.findByRole('option', { name: 'claude-opus-5-5' })
      ).toBeVisible();
      await userEvent.keyboard('{Escape}');

      await userEvent.click(screen.getByLabelText(labelClose));
      await waitFor(() => expect(getSaveButton()).toBeNull());

      await userEvent.click(await screen.findByText('Anthropic production'));
      await waitForRequest('@getAiProvider');

      await userEvent.click(await getCustomInput(labelDefaultModel));

      expect(screen.queryByRole('option')).toBeNull();
    });

    it('does not offer the models discovered for a provider closed while they were fetched', async () => {
      await openProvider();

      let release = (): void => undefined;
      const released = new Promise<void>((resolve) => {
        release = resolve;
      });

      interceptRequest(
        Method.POST,
        `**${discoverModelsEndpoint}`,
        async () => {
          await released;

          return HttpResponse.json(discoveredModelsResponse);
        },
        'slowDiscoverModels'
      );
      // same type as the first provider, so the models are not filtered out
      // by type
      interceptAPIRequest({
        alias: 'getOtherAiProvider',
        method: Method.GET,
        path: `**${getAiProviderEndpoint({ id: 2 })}`,
        response: {
          ...getAiProviderResponse(),
          '@id': '/centreon/api/latest/administration/ai-providers/2',
          id: 2,
          name: 'Mistral fallback'
        }
      });

      await waitFor(() =>
        expect(screen.getByTestId(labelFetchModels)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchModels));
      await waitFor(() =>
        expect(getRequestCalls('@slowDiscoverModels')).toHaveLength(1)
      );

      await userEvent.click(screen.getByLabelText(labelClose));
      await waitFor(() => expect(getSaveButton()).toBeNull());

      await userEvent.click(await screen.findByText('Mistral fallback'));
      await waitForRequest('@getOtherAiProvider');
      await waitFor(async () =>
        expect(await getName()).toHaveValue('Mistral fallback')
      );

      release();
      await waitForRequest('@slowDiscoverModels');
      await new Promise((resolve) => {
        setTimeout(resolve, 100);
      });

      await userEvent.click(await getCustomInput(labelDefaultModel));

      expect(screen.queryByRole('option')).toBeNull();
    });

    it('keeps the token untouched when a provider is edited without typing a new token', async () => {
      await openProvider();

      await waitFor(async () =>
        expect(await getName()).toHaveValue('Anthropic production')
      );

      // the stored token is masked and locked, signalling it is configured
      // without exposing it
      const token = await getApiToken();

      expect(token).toHaveValue('********');
      expect(token).toBeDisabled();

      await replaceValue(await getName(), 'Anthropic renamed');

      await save();

      const { request } = await waitForRequest('@updateAiProvider');
      const body = request.body as Payload;

      expect(body.name).toBe('Anthropic renamed');
      expect(body).not.toHaveProperty('api_token');
    });

    it('asks for the token again when the endpoint of a provider is changed', async () => {
      await openProvider({
        aiProvider: {
          ...getAiProviderResponse(),
          endpoint_url: 'https://llm-gateway.example.com',
          type: 'openai_compatible'
        }
      });

      await waitFor(async () =>
        expect(await getApiToken()).toHaveValue('********')
      );
      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );

      await replaceValue(
        (await queryAllInPanel(endpointUrlTestId)).at(-1) as HTMLElement,
        'https://other-gateway.example.com'
      );

      // the stored token is never sent to another endpoint: it must be typed
      await waitFor(async () => expect(await getApiToken()).toHaveValue(''));
      expect(await getApiToken()).toBeEnabled();
      expect(
        await screen.findByText(labelApiTokenRequiredForNewEndpoint)
      ).toBeVisible();
      expect(screen.getByTestId(labelTestConnection)).toBeDisabled();
      expect(screen.getByTestId(labelFetchModels)).toBeDisabled();
      expect(getSaveButton()).toBeDisabled();

      await userEvent.type(await getApiToken(), 'a-new-token');
      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );

      await save();

      const { request } = await waitForRequest('@updateAiProvider');
      const body = request.body as Payload;

      expect(body.endpoint_url).toBe('https://other-gateway.example.com');
      expect(body.api_token).toBe('a-new-token');
    });
    it('prefills the prices found for the models', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      const release = interceptSlowPrices();

      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchPrices));
      release();
      await waitForRequest('@slowPrices');

      expect(await screen.findByText(labelPricesFilled)).toBeVisible();
      // number inputs hold numbers
      await waitFor(async () =>
        expect(await getCustomInput(labelDefaultModelInputPrice)).toHaveValue(3)
      );
      expect(await getCustomInput(labelDefaultModelOutputPrice)).toHaveValue(
        15
      );
      await expectValue(labelCurrency, 'USD');
    });

    it('does not apply the prices found for a model changed meanwhile', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);

      const release = interceptSlowPrices();

      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchPrices));
      await waitFor(() =>
        expect(getRequestCalls('@slowPrices')).toHaveLength(1)
      );

      await replaceValue(await getCustomInput(labelDefaultModel), 'other');

      release();
      await waitForRequest('@slowPrices');
      await new Promise((resolve) => {
        setTimeout(resolve, 100);
      });

      expect(screen.queryByText(labelPricesFilled)).toBeNull();
      expect(await getCustomInput(labelDefaultModelInputPrice)).toHaveValue(
        null
      );
      expect(await getCustomInput(labelCurrency)).toHaveValue('');
    });

    it('sets the currency to USD when it was cleared while the prices were fetched', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getCustomInput(labelCurrency), 'USD{Escape}');
      await expectValue(labelCurrency, 'USD');

      const release = interceptSlowPrices();

      await waitFor(() =>
        expect(screen.getByTestId(labelFetchPrices)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelFetchPrices));
      await waitFor(() =>
        expect(getRequestCalls('@slowPrices')).toHaveLength(1)
      );

      await userEvent.clear(await getCustomInput(labelCurrency));
      await expectValue(labelCurrency, '');

      release();
      await waitForRequest('@slowPrices');

      expect(await screen.findByText(labelPricesFilled)).toBeVisible();
      await expectValue(labelCurrency, 'USD');
    });

    it('shows an error next to the button when the connection test answer cannot be read', async () => {
      await openCreationForm();
      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      interceptRequest(
        Method.POST,
        `**${testConnectionEndpoint}`,
        () =>
          new HttpResponse('<html>Bad gateway</html>', {
            headers: { 'Content-Type': 'application/json' },
            status: 200
          }),
        'unreadableTestConnection'
      );

      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toBeEnabled()
      );
      await userEvent.click(screen.getByTestId(labelTestConnection));
      await waitForRequest('@unreadableTestConnection');

      expect(await screen.findByText(labelUnreadableAnswer)).toBeVisible();
      await waitFor(() =>
        expect(screen.getByTestId(labelTestConnection)).toHaveTextContent(
          labelTestConnection
        )
      );
    });

    it('clears the endpoint when the provider type changes', async () => {
      await openCreationForm();
      await selectType(labelOpenAiCompatible);

      await userEvent.type(
        (await queryAllInPanel(endpointUrlTestId)).at(-1) as HTMLElement,
        'https://llm-gateway.example.com'
      );

      // an OpenAI-compatible URL is not a Bedrock endpoint
      await selectType(labelBedrock);
      await expectValue(labelAwsRegion, '');

      await selectType(labelAnthropic);
      await userEvent.type(await getApiToken(), 'a-very-secret-token');

      await save();

      const { request } = await waitForRequest('@createAiProvider');

      expect(request.body as Payload).not.toHaveProperty('endpoint_url');
    });
  });
};
