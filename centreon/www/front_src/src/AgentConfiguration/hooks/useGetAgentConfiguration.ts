import { useFetchQuery } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { equals, isEmpty, isNil, isNotNil, map, or } from 'ramda';
import { useEffect } from 'react';

import { agentConfigurationDecoder } from '../api/decoders';
import { getAgentConfigurationEndpoint } from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
import { agentTypes, connectionModes } from '../Form/useInputs';
import {
  AgentConfiguration,
  AgentConfigurationForm,
  AgentType
} from '../models';

const adaptAgentConfigurationToForm = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationForm => ({
  ...agentConfiguration,
  configuration: {
    ...agentConfiguration.configuration,
    ...(equals(AgentType.CMA, agentConfiguration.type) && {
      hosts: map(
        (host) => ({
          ...host,
          token: or(isNil(host.token), isEmpty(host.token))
            ? null
            : {
                id: `${host.token?.name}_${host.token?.creatorId}`,
                ...host.token
              }
        }),
        agentConfiguration.configuration?.hosts || []
      ),
      tokens: map(
        ({ name, creatorId }) => ({
          creatorId,
          id: `${name}_${creatorId}`,
          name
        }),
        agentConfiguration.configuration?.tokens || []
      )
    })
  },
  connectionMode: connectionModes.find(({ id }) =>
    equals(id, agentConfiguration.connectionMode)
  ),
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
    decoder: agentConfigurationDecoder,
    getEndpoint: () => getAgentConfigurationEndpoint(id),
    getQueryKey: () => ['agent-configuration', id],
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
