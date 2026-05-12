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
  AgentType,
  CMAConfiguration,
  HostConfiguration
} from '../models';

const adaptAgentConfigurationToForm = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationForm => {
  const cmaConf = agentConfiguration.configuration as CMAConfiguration;
  return {
    ...agentConfiguration,
    configuration: {
      ...agentConfiguration.configuration,
      ...(equals(AgentType.CMA, agentConfiguration.type) && {
        hosts: map(
          (host: HostConfiguration) => ({
            ...host,
            token: or(isNil(host.token), isEmpty(host.token))
              ? null
              : {
                  id: `${host.token?.name}_${host.token?.creatorId}`,
                  ...host.token
                }
          }),
          cmaConf?.hosts || []
        ),
        tokens: map(
          ({ name, creatorId }: { name: string; creatorId: number }) => ({
            creatorId,
            id: `${name}_${creatorId}`,
            name
          }),
          cmaConf?.tokens || []
        )
      })
    } as AgentConfigurationForm['configuration'],
    connectionMode: connectionModes.find(({ id }) =>
      equals(id, agentConfiguration.connectionMode)
    ) as AgentConfigurationForm['connectionMode'],
    type:
      agentTypes.find(({ id }) => equals(id, agentConfiguration.type)) ?? null
  };
};

interface UseGetAgentConfigurationState {
  initialValues?: AgentConfigurationForm;
  isLoading: boolean;
}

export const useGetAgentConfiguration = (
  id: number | 'add' | null
): UseGetAgentConfigurationState => {
  const setAgentTypeForm = useSetAtom(agentTypeFormAtom);

  const enabled = isNotNil(id) && !equals('add', id as unknown);

  const { data, isLoading } = useFetchQuery<AgentConfiguration>({
    decoder: agentConfigurationDecoder,
    getEndpoint: () => getAgentConfigurationEndpoint(id as number),
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
