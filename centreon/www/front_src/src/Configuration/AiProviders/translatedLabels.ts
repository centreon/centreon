export const labelAiProviders = 'AI Providers';
export const labelAiProvidersDescription =
  'Connect the AI features to the LLM provider of your choice.';
export const labelAddAiProvider = 'Add provider configuration';
export const labelWelcomePageTitle = 'Welcome to AI provider configurations';
export const labelWelcomePageDescription =
  'Configure the AI providers used by AI features. Requests made by AI features send monitoring data (such as host names and check outputs) to the configured provider.';

export const labelName = 'Name';
export const labelType = 'Type';
export const labelTypePlaceholder = 'Select a provider type';
export const labelEndpointUrl = 'Endpoint URL';
export const labelApiToken = 'API token';
export const labelDefaultModel = 'Default model';
export const labelDefaultModelPlaceholder =
  'e.g. claude-sonnet-5-5, mistral-large-latest, gpt-6.2-sol';
export const labelFastModel = 'Fast model';
export const labelFastModelPlaceholder =
  'e.g. claude-haiku-5-5, mistral-small-latest, gpt-6-luna — defaults to the default model';
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
export const labelApiTokenRequiredForNewEndpoint =
  'Enter the API token again to use it with this endpoint';
export const labelNameAlreadyExists =
  'An AI provider with this name already exists';

export const labelUsage = 'Usage';
export const labelTodayUsage = 'Today: {{used}}';
export const labelTodayUsageOfLimit = 'Today: {{used}} / {{limit}}';
export const labelThisMonthUsage = 'This month: {{used}}';
export const labelThisMonthUsageOfLimit = 'This month: {{used}} / {{limit}}';
export const labelSpentThisMonth = '{{amount}} this month';
export const labelLimitUsedToday = '{{used}} / {{limit}} ({{percent}}%) today';
export const labelLimitUsedThisMonth =
  '{{used}} / {{limit}} ({{percent}}%) this month';
export const labelNotMetered = 'Not metered';

export const labelTestConnection = 'Test connection';
export const labelTestingConnection = 'Testing connection…';
export const labelConnectionSucceeded = 'Connection successful';
export const labelConnectionSucceededWithModel =
  'Connection successful ({{model}})';
export const labelConnectionFailed = 'Connection failed';
export const labelTestConnectionHint =
  'Sends a minimal request to the provider (billed by the provider, not counted toward the spend limit)';

export const labelFetchModels = 'Fetch models';
export const labelFetchingModels = 'Fetching models…';
export const labelModelsFetched = 'Models fetched from the provider';
export const labelNoModelsFound = 'The provider returned no models';
export const labelFetchModelsFailed = 'Could not fetch the models';
export const labelFetchModelsHint =
  'Lists the models the provider exposes, using the token entered above. Doubles as a connection test.';

export const labelFetchPrices = 'Prefill model prices';
export const labelFetchingPrices = 'Fetching prices…';
export const labelFetchPricesHint =
  'Downloads current USD prices from the LiteLLM catalog on github.com (a third party). Available only when the currency is USD.';
export const labelPricesFilled = 'Prices prefilled (USD)';
export const labelPricesNotFound = 'No published prices found for these models';
export const labelFetchPricesFailed = 'Could not fetch the prices';
export const labelUnreadableAnswer = "The server's answer could not be read";

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
