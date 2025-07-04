import { buildListingEndpoint } from '@centreon/ui';
import dayjs from 'dayjs';

export const getAgentConfigurationsEndpoint =
  '/configuration/agent-configurations';

export const pollersEndpoint = '/configuration/monitoring-servers';
export const agentConfigurationPollersEndpoint = `${getAgentConfigurationsEndpoint}/pollers`;

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });

interface GetPollerAgentEndpointProps {
  agentId: number;
  pollerId?: number;
}

export const getPollerAgentEndpoint = ({
  agentId,
  pollerId
}: GetPollerAgentEndpointProps): string =>
  `${getAgentConfigurationsEndpoint}/${agentId}${pollerId ? `/pollers/${pollerId}` : ''}`;

export const getAgentConfigurationEndpoint = (id: number) =>
  `${getAgentConfigurationsEndpoint}/${id}`;

export const hostsConfigurationEndpoint = '/configuration/hosts';

export const listTokensEndpoint = '/administration/tokens';

export const tokensSearchConditions = [
  {
    field: 'type',
    values: {
      $eq: 'cma'
    }
  },
  {
    field: 'is_revoked',
    values: {
      $eq: false
    }
  },
  {
    field: 'expiration_date',
    values: {
      $ge: dayjs(Date.now()),
      $eq: null
    }
  }
];

export const getTokensEndpoint = (parameters): string => {
  return buildListingEndpoint({
    baseEndpoint: listTokensEndpoint,
    parameters: {
      ...parameters,
      search: {
        conditions: [
          ...(parameters?.search?.conditions || []),
          ...tokensSearchConditions
        ]
      }
    }
  });
};
