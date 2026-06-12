export const aiProvidersEndpoint = '/administration/ai-providers';

export const getAiProviderEndpoint = ({
  id
}: {
  id: number | string;
}): string => `${aiProvidersEndpoint}/${id}`;
