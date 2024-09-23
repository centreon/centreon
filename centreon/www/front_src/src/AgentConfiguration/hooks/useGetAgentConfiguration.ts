import { useFetchQuery } from '@centreon/ui';
import { useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { agentTypes } from '../Form/useInputs';
import { agentConfigurationDecoder } from '../api/decoders';
import { getAgentConfigurationEndpoint } from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
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
  const setAgentTypeForm = useSetAtom(agentTypeFormAtom);

  const { data, isLoading } = useFetchQuery({
    baseEndpoint: 'http://localhost:3001/centreon/api/latest',
    getEndpoint: () => getAgentConfigurationEndpoint(id),
    getQueryKey: () => ['agent-configuration', id],
    decoder: agentConfigurationDecoder,
    queryOptions: {
      enabled: isNotNil(id) && !equals('add', id),
      suspense: false
    }
  });

  setAgentTypeForm(data?.type || null);

  return {
    initialValues: data ? adaptAgentConfigurationToForm(data) : undefined,
    isLoading
  };
};
