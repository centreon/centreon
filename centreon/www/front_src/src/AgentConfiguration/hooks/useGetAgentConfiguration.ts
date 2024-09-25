import { useFetchQuery } from '@centreon/ui';
import { equals, isNotNil } from 'ramda';
import { agentTypes } from '../Form/useInputs';
import { agentConfigurationDecoder } from '../api/decoders';
import { getAgentConfigurationEndpoint } from '../api/endpoints';
import { AgentConfiguration, AgentConfigurationForm } from '../models';

const adaptAgentConfigurationToForm = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationForm => ({
  ...agentConfiguration,
  type: agentTypes.find(({ id }) => equals(id, agentConfiguration.type))
});

interface UseGetAgentConfigurationState {
  initialValues?: AgentConfigurationForm;
  isLoading: boolean;
}

export const useGetAgentConfiguration = (
  id: number | 'add' | null
): UseGetAgentConfigurationState => {
  const { data, isLoading } = useFetchQuery({
    getEndpoint: () => getAgentConfigurationEndpoint(id),
    getQueryKey: () => ['agent-configuration', id],
    decoder: agentConfigurationDecoder,
    queryOptions: {
      enabled: isNotNil(id) && !equals('add', id),
      suspense: false
    }
  });

  return {
    initialValues: data ? adaptAgentConfigurationToForm(data) : undefined,
    isLoading
  };
};
