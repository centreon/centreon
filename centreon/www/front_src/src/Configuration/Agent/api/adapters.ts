import { SelectEntry } from '@centreon/ui';
import { equals, isEmpty, isNil, map, omit, or, pluck } from 'ramda';

import {
  AgentConfiguration,
  AgentConfigurationAPI,
  AgentConfigurationForm,
  AgentType,
  CMAConfiguration,
  ConnectionMode,
  TelegrafConfiguration
} from '../models';

import { agentTypes, connectionModes } from '../utils';

export const adaptTelegrafConfigurationToAPI = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationAPI => {
  const configuration =
    agentConfiguration.configuration as TelegrafConfiguration;

  const getFieldBasedOnCertificate = (field) =>
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.secure) ||
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.insecure)
      ? field
      : null;

  return {
    ...omit(['pollers', 'connectionMode'], agentConfiguration),
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    type: (agentConfiguration.type as SelectEntry).id,
    configuration: {
      otel_private_key: getFieldBasedOnCertificate(
        configuration.otelPrivateKey
      ),
      otel_ca_certificate: getFieldBasedOnCertificate(
        configuration.otelCaCertificate
      ),
      otel_public_certificate: getFieldBasedOnCertificate(
        configuration.otelPublicCertificate
      ),
      conf_certificate: getFieldBasedOnCertificate(
        configuration.confCertificate
      ),
      conf_private_key: getFieldBasedOnCertificate(
        configuration.confPrivateKey
      ),
      conf_server_port: configuration.confServerPort
    }
  };
};

export const adaptCMAConfigurationToAPI = (
  agentConfiguration: AgentConfiguration
): AgentConfigurationAPI => {
  const configuration = agentConfiguration.configuration as CMAConfiguration;

  const getFieldBasedOnCertificate = (field) =>
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.secure) ||
    equals(agentConfiguration?.connectionMode?.id, ConnectionMode.insecure)
      ? field
      : null;

  return {
    ...omit(['pollers', 'connectionMode'], agentConfiguration),
    connection_mode: agentConfiguration?.connectionMode?.id,
    poller_ids: pluck('id', agentConfiguration.pollers) as Array<number>,
    type: (agentConfiguration.type as SelectEntry).id,
    configuration: {
      is_reverse: configuration.isReverse,
      tokens:
        equals(agentConfiguration?.connectionMode?.id, 'no-tls') ||
        configuration.isReverse
          ? []
          : map(
              ({ name, creatorId }) => ({ name, creator_id: creatorId }),
              agentConfiguration.configuration.tokens
            ),
      otel_ca_certificate: getFieldBasedOnCertificate(
        configuration.otelCaCertificate
      ),
      otel_public_certificate: getFieldBasedOnCertificate(
        configuration.otelPublicCertificate
      ),
      otel_private_key: getFieldBasedOnCertificate(
        configuration.otelPrivateKey
      ),
      hosts: configuration.hosts.map((host) => ({
        id: host.id,
        address: host.address,
        port: host.port,
        poller_ca_name: getFieldBasedOnCertificate(host.pollerCaName),
        poller_ca_certificate: getFieldBasedOnCertificate(
          host.pollerCaCertificate
        ),
        token:
          equals(agentConfiguration?.connectionMode?.id, 'no-tls') ||
          !configuration.isReverse
            ? null
            : {
                name: host?.token?.name,
                creator_id: host?.token?.creatorId
              }
      }))
    }
  };
};

export const adaptAgentConfigurationToForm = (
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
                token: or(isNil(host.token), isEmpty(host.token))
                  ? null
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
