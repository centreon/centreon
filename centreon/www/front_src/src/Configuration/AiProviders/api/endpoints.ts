export const aiProvidersEndpoint = '/administration/ai-providers';

export const testConnectionEndpoint = `${aiProvidersEndpoint}/test-connection`;

export const modelPricesEndpoint = `${aiProvidersEndpoint}/model-prices`;

export const getAiProviderEndpoint = ({
  id
}: {
  id: number | string;
}): string => `${aiProvidersEndpoint}/${id}`;
