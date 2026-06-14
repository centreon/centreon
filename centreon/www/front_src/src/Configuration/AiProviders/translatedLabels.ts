export const labelAiProviders = 'AI Providers';
export const labelAddAiProvider = 'Add provider configuration';
export const labelWelcomePageTitle = 'Welcome to AI provider configurations';
export const labelWelcomePageDescription =
  'Configure the AI providers used by AI features. Requests made by AI features send monitoring data (such as host names and check outputs) to the configured provider.';

export const labelName = 'Name';
export const labelType = 'Type';
export const labelEndpointUrl = 'Endpoint URL';
export const labelApiToken = 'API token';
export const labelApiTokenHint =
  'The token is write-only: leave the field empty to keep the current token.';
export const labelDefaultModel = 'Default model';
export const labelDefaultModelPlaceholder =
  'e.g. claude-sonnet-4-6, mistral-medium-latest, gpt-5.4';
export const labelFastModel = 'Fast model';
export const labelFastModelPlaceholder =
  'e.g. claude-haiku-4-5, mistral-small-latest, gpt-5.4-mini — defaults to the default model';
export const labelDescription = 'Description';
export const labelEnabled = 'Enabled';

export const labelAnthropic = 'Anthropic';
export const labelMistral = 'Mistral';
export const labelOpenAi = 'OpenAI';
export const labelGoogle = 'Google Gemini';
export const labelBedrock = 'Amazon Bedrock';
export const labelOpenAiCompatible = 'OpenAI-compatible';

export const labelAwsRegion = 'AWS region';
export const labelAwsRegionPlaceholder = 'e.g. eu-west-1, eu-west-3, us-east-1';

export const labelGeneralInformation = 'General information';
export const labelConnection = 'Connection';
export const labelModels = 'Models';

export const labelRequired = 'Required';
export const labelInvalidUrl = 'The endpoint must be a valid URL';
export const labelNameAlreadyExists =
  'An AI provider with this name already exists';

export const labelUsage = 'Usage';
export const labelToday = 'today';
export const labelThisMonth = 'this month';
export const labelNotMetered = 'Not metered';

export const labelTestConnection = 'Test connection';
export const labelTestingConnection = 'Testing connection…';
export const labelConnectionSucceeded = 'Connection successful';
export const labelConnectionFailed = 'Connection failed';
export const labelTestConnectionHint =
  'Sends a minimal request to the provider (billed by the provider, not counted toward the spend limit)';

export const labelFetchPrices = 'Fill prices from LiteLLM';
export const labelFetchingPrices = 'Fetching prices…';
export const labelFetchPricesHint =
  'Downloads current USD prices from LiteLLM (github.com, a third party). Available only when the currency is USD.';
export const labelPricesFilled = 'Prices filled from LiteLLM (USD)';
export const labelPricesNotFound = 'No published prices found for these models';
export const labelFetchPricesFailed = 'Could not fetch the prices';

export const labelPricing = 'Pricing';
export const labelSpendLimits = 'Spend limits';
export const labelCurrency = 'Currency';
export const labelCurrencyPlaceholder = 'e.g. USD, EUR';
export const labelInvalidCurrency = 'Use a 3-letter ISO code, e.g. USD';
export const labelDefaultModelInputPrice =
  'Default model price per 1M input tokens';
export const labelDefaultModelOutputPrice =
  'Default model price per 1M output tokens';
export const labelFastModelInputPrice = 'Fast model price per 1M input tokens';
export const labelFastModelOutputPrice =
  'Fast model price per 1M output tokens';
export const labelDailyLimit = 'Daily spend limit';
export const labelMonthlyLimit = 'Monthly spend limit';
export const labelCurrencyRequired =
  'A currency is required to set prices or spend limits';
export const labelPricingRequiredForLimit =
  'Set the default model prices to use a spend limit';
export const labelCurrencyChangeResetsUsage =
  'Only usage recorded in the selected currency counts toward the limit, so the current daily and monthly totals will change. Past usage is kept for reporting.';
