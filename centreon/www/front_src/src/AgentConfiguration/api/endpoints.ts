import { buildListingEndpoint } from '@centreon/ui';

export const getAgentConfigurationsEndpoint =
  '/configuration/agent_configurations';

export const pollersEndpoint = '/configuration/monitoring-servers';

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
