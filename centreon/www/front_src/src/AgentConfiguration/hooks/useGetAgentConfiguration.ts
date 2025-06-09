import { useEffect } from 'react';

import { useFetchQuery } from '@centreon/ui';
import { useSetAtom } from 'jotai';
import { equals, isEmpty, isNil, isNotNil, map } from 'ramda';

import { agentTypes, connectionModes } from '../Form/useInputs';
import { agentConfigurationDecoder } from '../api/decoders';
import { getAgentConfigurationEndpoint } from '../api/endpoints';
import { agentTypeFormAtom } from '../atoms';
import {
  AgentConfiguration,
  AgentConfigurationForm,
  AgentType
} from '../models';

const adaptAgentConfigurationToForm = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationForm => ({
  ...agentConfiguration,
  type: agentTypes.find(({ id }) => equals(id, agentConfiguration.type)),
  connectionMode: connectionModes.find(({ id }) =>
    equals(id, agentConfiguration.connectionMode)
  ),
  configuration: {
    ...agentConfiguration.configuration,
    ...(equals(AgentType.CMA, agentConfiguration.type) &&
      (!agentConfiguration.configuration.isReverse
        ? {
            tokens: map(
              ({ name, creatorId }) => ({
                id: `${name}_${creatorId}`,
                name,
                creatorId
              }),
              agentConfiguration.configuration?.tokens || []
            )
          }
        : {
            hosts: map(
              (host) => ({
                ...host,
                token:
                  isNil(host.token) && isEmpty(host.token)
                    ? host.token
                    : {
                        id: `${host.token?.name}_${host.token?.creatorId}`,
                        ...host.token
                      }
              }),
              agentConfiguration.configuration?.hosts
            )
          }))
  }
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
