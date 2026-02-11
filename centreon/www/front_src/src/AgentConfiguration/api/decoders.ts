import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import {
  AgentConfiguration,
  AgentType,
  CMAConfiguration,
  InstallationCommand,
  TelegrafConfiguration
} from '../models';

export const agentConfigurationsListingDecoder = buildListingDecoder({
  entityDecoder: JsonDecoder.object(
    {
      id: JsonDecoder.number,
      isAgentInitiated: JsonDecoder.optional(JsonDecoder.boolean),
      name: JsonDecoder.string,
      pollers: JsonDecoder.array(
        JsonDecoder.object(
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

const cmaConfigurationDecoder = JsonDecoder.object<CMAConfiguration>(
  {
    agentInitiated: JsonDecoder.boolean,
    hosts: JsonDecoder.array(
      JsonDecoder.object(
        {
          address: JsonDecoder.string,
          id: JsonDecoder.optional(JsonDecoder.number),
          name: JsonDecoder.optional(JsonDecoder.string),
          pollerCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
          pollerCaName: JsonDecoder.nullable(JsonDecoder.string),
          port: JsonDecoder.number,
          token: JsonDecoder.optional(
            JsonDecoder.object(
              {
                creatorId: JsonDecoder.number,
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
        JsonDecoder.object(
          {
            creatorId: JsonDecoder.number,
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
    connectionMode: JsonDecoder.string,
    name: JsonDecoder.string,
    pollers: JsonDecoder.array(
      JsonDecoder.object(
        {
          id: JsonDecoder.number,
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
    connectionMode: 'connection_mode'
  }
);

export const installationCommandDecoder =
  JsonDecoder.object<InstallationCommand>(
    {
      id: JsonDecoder.number,
      linuxScriptCommand: JsonDecoder.string,
      linuxScriptURL: JsonDecoder.string,
      windowsScriptCommand: JsonDecoder.string,
      windowsScriptURL: JsonDecoder.string
    },
    'Agent configuration',
    {
      id: 'poller_id',
      linuxScriptCommand: 'linux_script_command',
      linuxScriptURL: 'linux_script_url',
      windowsScriptCommand: 'windows_script_command',
      windowsScriptURL: 'windows_script_url'
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
