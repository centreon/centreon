import { buildListingEndpoint } from '@centreon/ui';
import dayjs from 'dayjs';

export const agentConfigurationsEndpoint =
  '/configuration/agent-configurations';

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });

interface GetPollerAgentEndpointProps {
  id: number;
  subItemId?: number;
}

export const getPollerAgentEndpoint = ({
  id,
  subItemId
}: GetPollerAgentEndpointProps): string =>
  `${agentConfigurationsEndpoint}/${id}${subItemId ? `/pollers/${subItemId}` : ''}`;

export const getAgentConfigurationEndpoint = ({ id }): string =>
  `${agentConfigurationsEndpoint}/${id}`;

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
