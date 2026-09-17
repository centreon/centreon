import { buildListingEndpoint, type ListingParameters } from '@centreon/ui';

import dayjs from 'dayjs';

export const getAgentConfigurationsEndpoint =
  '/configuration/agent-configurations';

export const pollersEndpoint = '/configuration/monitoring-servers';
export const agentConfigurationPollersEndpoint = `${getAgentConfigurationsEndpoint}/pollers`;

export const getPollersEndpoint = (parameters: ListingParameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });

export interface GetPollerAgentEndpointProps {
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

export const getInstallationCommandEndpoint = (id: number) =>
  `/configuration/agent-configurations/installation-command/${id}`;

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
      $eq: null,
      $ge: dayjs(Date.now())
    }
  }
];

export const getTokensEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: listTokensEndpoint,
    parameters: {
      ...parameters,
      search: {
        conditions: [
          ...(parameters?.search?.conditions || []),
          ...(tokensSearchConditions as NonNullable<
            NonNullable<
              Parameters<typeof buildListingEndpoint>[0]['parameters']['search']
            >['conditions']
          >)
        ]
      }
    }
  });
};

export const getHostsEndpoint = (parameters: ListingParameters): string => {
  const condition = parameters?.search?.conditions?.[1];

  return buildListingEndpoint({
    baseEndpoint: hostsConfigurationEndpoint,
    parameters: {
      ...parameters,
      search: {
        conditions: condition ? [condition] : []
      }
    }
  });
};
