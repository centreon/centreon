import { useFetchQuery } from '@centreon/ui';
import { useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useEffect } from 'react';
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

  const enabled = isNotNil(id) && !equals('add', id);

  const { data, isLoading } = useFetchQuery({
    getEndpoint: () => getAgentConfigurationEndpoint(id),
    getQueryKey: () => ['agent-configuration', id],
    decoder: agentConfigurationDecoder,
    queryOptions: {
      enabled,
      suspense: false
    }
  });

  useEffect(() => {
    if (!data || !enabled) {
      return;
    }
    setAgentTypeForm(data.type);
  }, [data, enabled]);

  return {
    initialValues: data ? adaptAgentConfigurationToForm(data) : undefined,
    isLoading
  };
};
