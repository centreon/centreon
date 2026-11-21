import { adaptFormToApiPayload } from './useAiProviders';

// What the form holds for a new Anthropic provider with nothing optional set.
const formValues = {
  apiToken: '',
  currency: null,
  defaultModel: 'claude-sonnet-5-5',
  description: null,
  endpointUrl: '',
  fastModel: null,
  isActivated: false,
  limitDaily: null,
  limitMonthly: null,
  name: 'Anthropic',
  priceDefaultInput: null,
  priceDefaultOutput: null,
  priceFastInput: null,
  priceFastOutput: null,
  type: 'anthropic'
};

describe('AI provider payload', () => {
  it('sends every field of a provider with nothing optional set', () => {
    expect(adaptFormToApiPayload(formValues)).toEqual({
      currency: null,
      default_model: 'claude-sonnet-5-5',
      description: null,
      fast_model: null,
      is_activated: false,
      limit_daily: null,
      limit_monthly: null,
      name: 'Anthropic',
      price_default_input: null,
      price_default_output: null,
      price_fast_input: null,
      price_fast_output: null,
      type: 'anthropic'
    });
  });

  it('sends the name trimmed', () => {
    expect(
      adaptFormToApiPayload({ ...formValues, name: '  My provider \t' }).name
    ).toBe('My provider');
  });

  it('leaves out an empty token, so the stored one is kept', () => {
    expect(adaptFormToApiPayload(formValues)).not.toHaveProperty('api_token');
    expect(
      adaptFormToApiPayload({ ...formValues, apiToken: undefined })
    ).not.toHaveProperty('api_token');
  });

  it('sends a typed token', () => {
    expect(
      adaptFormToApiPayload({ ...formValues, apiToken: 'a-secret' }).api_token
    ).toBe('a-secret');
  });

  it('sends the endpoint only when one is set', () => {
    expect(adaptFormToApiPayload(formValues)).not.toHaveProperty(
      'endpoint_url'
    );
    expect(
      adaptFormToApiPayload({
        ...formValues,
        endpointUrl: 'https://llm-gateway.example.com',
        type: 'openai_compatible'
      }).endpoint_url
    ).toBe('https://llm-gateway.example.com');
  });

  it('sends the prices and limits as numbers, decimals included', () => {
    const payload = adaptFormToApiPayload({
      ...formValues,
      currency: 'USD',
      limitDaily: '10',
      limitMonthly: 50,
      priceDefaultInput: '0.01',
      priceDefaultOutput: '15',
      priceFastInput: 0.5,
      priceFastOutput: '0'
    });

    expect(payload).toMatchObject({
      currency: 'USD',
      limit_daily: 10,
      limit_monthly: 50,
      price_default_input: 0.01,
      price_default_output: 15,
      price_fast_input: 0.5,
      price_fast_output: 0
    });
  });

  it('sends a cleared or unreadable number as null', () => {
    const payload = adaptFormToApiPayload({
      ...formValues,
      limitDaily: '',
      limitMonthly: undefined,
      priceDefaultInput: 'abc',
      priceDefaultOutput: null
    });

    expect(payload).toMatchObject({
      limit_daily: null,
      limit_monthly: null,
      price_default_input: null,
      price_default_output: null
    });
  });

  it('sends an empty currency, description or fast model as null', () => {
    const payload = adaptFormToApiPayload({
      ...formValues,
      currency: '',
      description: '',
      fastModel: ''
    });

    expect(payload).toMatchObject({
      currency: null,
      description: null,
      fast_model: null
    });
  });

  it('sends the description, fast model and activation as filled', () => {
    const payload = adaptFormToApiPayload({
      ...formValues,
      description: 'Main provider',
      fastModel: 'claude-haiku-5-5',
      isActivated: true
    });

    expect(payload).toMatchObject({
      description: 'Main provider',
      fast_model: 'claude-haiku-5-5',
      is_activated: true
    });
  });
});
