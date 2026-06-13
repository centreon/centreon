export const aiProvidersEndpoint = '/administration/ai-providers';

export const testConnectionEndpoint = `${aiProvidersEndpoint}/test-connection`;

export const getAiProviderEndpoint = ({
  id
}: {
  id: number | string;
}): string => `${aiProvidersEndpoint}/${id}`;
