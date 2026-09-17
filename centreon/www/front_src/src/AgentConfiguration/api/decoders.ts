import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import {
  AgentConfiguration,
  AgentConfigurationListing,
  AgentType,
  CMAConfiguration,
  ConnectionMode,
  HostConfiguration,
  InstallationCommand,
  TelegrafConfiguration
} from '../models';

interface PollerEntry {
  id: number;
  isCentral?: boolean;
  name: string;
}

export const agentConfigurationsListingDecoder = buildListingDecoder({
  entityDecoder: JsonDecoder.object<AgentConfigurationListing>(
    {
      id: JsonDecoder.number,
      isAgentInitiated: JsonDecoder.optional(
        JsonDecoder.boolean
      ) as JsonDecoder.Decoder<boolean>,
      name: JsonDecoder.string,
      pollers: JsonDecoder.array(
        JsonDecoder.object<PollerEntry>(
          {
            id: JsonDecoder.number,
            isCentral: JsonDecoder.optional(JsonDecoder.boolean),
            name: JsonDecoder.string
          },
          'poller',
          {
            isCentral: 'is_central'
          }
        ),
        'pollers'
      ),
      type: JsonDecoder.enumeration<AgentType>(AgentType, 'Agent type')
    },
    'Agent configuration',
    {
      isAgentInitiated: 'is_agent_initiated'
    }
  ),
  entityDecoderName: 'Listing agents configuration',
  listingDecoderName: 'Agents configuration'
});

const telegrafConfigurationDecoder = JsonDecoder.object<TelegrafConfiguration>(
  {
    confCertificate: JsonDecoder.nullable(JsonDecoder.string),
    confPrivateKey: JsonDecoder.nullable(JsonDecoder.string),
    confServerPort: JsonDecoder.number,
    otelCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
    otelPrivateKey: JsonDecoder.nullable(JsonDecoder.string),
    otelPublicCertificate: JsonDecoder.nullable(JsonDecoder.string)
  },
  'Telegraf configuration',
  {
    confCertificate: 'conf_certificate',
    confPrivateKey: 'conf_private_key',
    confServerPort: 'conf_server_port',
    otelCaCertificate: 'otel_ca_certificate',
    otelPrivateKey: 'otel_private_key',
    otelPublicCertificate: 'otel_public_certificate'
  }
);

interface TokenShape {
  id: number;
  creatorId: number;
  name: string;
}

const cmaConfigurationDecoder = JsonDecoder.object<CMAConfiguration>(
  {
    agentInitiated: JsonDecoder.boolean,
    createHostAuto: JsonDecoder.optional(JsonDecoder.boolean),
    hosts: JsonDecoder.array(
      JsonDecoder.object<HostConfiguration>(
        {
          address: JsonDecoder.string,
          id: JsonDecoder.optional(
            JsonDecoder.number
          ) as JsonDecoder.Decoder<number>,
          name: JsonDecoder.optional(
            JsonDecoder.string
          ) as JsonDecoder.Decoder<string>,
          pollerCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
          pollerCaName: JsonDecoder.nullable(JsonDecoder.string),
          port: JsonDecoder.number,
          token: JsonDecoder.optional(
            JsonDecoder.object<{ id: string; name: string; creatorId: number }>(
              {
                creatorId: JsonDecoder.number,
                id: JsonDecoder.succeed as JsonDecoder.Decoder<string>,
                name: JsonDecoder.string
              },
              'token',
              { creatorId: 'creator_id' }
            )
          )
        },
        'Host configuration',
        {
          pollerCaCertificate: 'poller_ca_certificate',
          pollerCaName: 'poller_ca_name'
        }
      ),
      'Host configurations'
    ),
    otelCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
    otelPrivateKey: JsonDecoder.nullable(JsonDecoder.string),
    otelPublicCertificate: JsonDecoder.nullable(JsonDecoder.string),
    pollerInitiated: JsonDecoder.boolean,
    port: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.number)),
    tokens: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object<TokenShape>(
          {
            creatorId: JsonDecoder.number,
            id: JsonDecoder.optional(
              JsonDecoder.number
            ) as JsonDecoder.Decoder<number>,
            name: JsonDecoder.string
          },
          'token',
          { creatorId: 'creator_id' }
        ),
        'tokens'
      )
    )
  },
  'CMA configuration',
  {
    agentInitiated: 'agent_initiated',
    createHostAuto: 'create_host_auto',
    otelCaCertificate: 'otel_ca_certificate',
    otelPrivateKey: 'otel_private_key',
    otelPublicCertificate: 'otel_public_certificate',
    pollerInitiated: 'poller_initiated'
  }
);

export const agentConfigurationDecoder = JsonDecoder.object<AgentConfiguration>(
  {
    configuration: JsonDecoder.oneOf<TelegrafConfiguration | CMAConfiguration>(
      [telegrafConfigurationDecoder, cmaConfigurationDecoder],
      'Agent configuration configuration'
    ),
    connectionMode: JsonDecoder.enumeration<ConnectionMode>(
      ConnectionMode,
      'Connection mode'
    ),
    isAgentInitiated: JsonDecoder.optional(
      JsonDecoder.boolean
    ) as JsonDecoder.Decoder<boolean>,
    name: JsonDecoder.string,
    pollers: JsonDecoder.array(
      JsonDecoder.object<{ id: number; name: string; isCentral?: boolean }>(
        {
          id: JsonDecoder.number,
          isCentral: JsonDecoder.optional(JsonDecoder.boolean),
          name: JsonDecoder.string
        },
        'poller'
      ),
      'pollers'
    ),
    type: JsonDecoder.enumeration<AgentType>(AgentType, 'Agent type')
  },
  'Agent configuration',
  {
    connectionMode: 'connection_mode',
    isAgentInitiated: 'is_agent_initiated'
  }
);

export const installationCommandDecoder =
  JsonDecoder.object<InstallationCommand>(
    {
      linuxScriptCommand: JsonDecoder.string,
      windowsScriptCommand: JsonDecoder.string
    },
    'Agent configuration',
    {
      linuxScriptCommand: 'linux_installation_command',
      windowsScriptCommand: 'windows_installation_command'
    }
  );

export const tokenDecoder = JsonDecoder.object(
  {
    creator: JsonDecoder.object(
      {
        id: JsonDecoder.number,
        name: JsonDecoder.string
      },
      'Creator'
    ),
    name: JsonDecoder.string
  },
  'ListedToken'
).map(({ name, creator }) => {
  return {
    creatorId: creator.id,
    id: `${name}_${creator?.id}`,
    name,
    token_name: name
  };
});

export const listTokensDecoder = buildListingDecoder({
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Tokens',
  listingDecoderName: 'listTokens'
});
