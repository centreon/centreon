import { buildListingDecoder } from '@centreon/ui';
import { JsonDecoder } from 'ts.data.json';
import {
  AgentConfiguration,
  AgentType,
  CMAConfiguration,
  TelegrafConfiguration
} from '../models';

export const agentConfigurationsListingDecoder = buildListingDecoder({
  entityDecoder: JsonDecoder.object(
    {
      id: JsonDecoder.number,
      name: JsonDecoder.string,
      type: JsonDecoder.enumeration<AgentType>(AgentType, 'Agent type'),
      pollers: JsonDecoder.array(
        JsonDecoder.object(
          {
            id: JsonDecoder.number,
            name: JsonDecoder.string,
            isCentral: JsonDecoder.optional(JsonDecoder.boolean)
          },
          'poller',
          {
            isCentral: 'is_central'
          }
        ),
        'pollers'
      )
    },
    'Agent configuration'
  ),
  entityDecoderName: 'Listing agents configuration',
  listingDecoderName: 'Agents configuration'
});

const telegrafConfigurationDecoder = JsonDecoder.object<TelegrafConfiguration>(
  {
    otelPrivateKey: JsonDecoder.nullable(JsonDecoder.string),
    otelPublicCertificate: JsonDecoder.nullable(JsonDecoder.string),
    otelCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
    confPrivateKey: JsonDecoder.nullable(JsonDecoder.string),
    confServerPort: JsonDecoder.number,
    confCertificate: JsonDecoder.nullable(JsonDecoder.string)
  },
  'Telegraf configuration',
  {
    otelPrivateKey: 'otel_private_key',
    otelCaCertificate: 'otel_ca_certificate',
    otelPublicCertificate: 'otel_public_certificate',
    confCertificate: 'conf_certificate',
    confPrivateKey: 'conf_private_key',
    confServerPort: 'conf_server_port'
  }
);

const cmaConfigurationDecoder = JsonDecoder.object<CMAConfiguration>(
  {
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
    ),
    isReverse: JsonDecoder.boolean,
    otelPublicCertificate: JsonDecoder.nullable(JsonDecoder.string),
    otelCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
    otelPrivateKey: JsonDecoder.nullable(JsonDecoder.string),
    hosts: JsonDecoder.array(
      JsonDecoder.object(
        {
          address: JsonDecoder.string,
          port: JsonDecoder.number,
          pollerCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
          pollerCaName: JsonDecoder.nullable(JsonDecoder.string),
          name: JsonDecoder.optional(JsonDecoder.string),
          id: JsonDecoder.optional(JsonDecoder.number)
        },
        'Host configuration',
        {
          pollerCaCertificate: 'poller_ca_certificate',
          pollerCaName: 'poller_ca_name'
        }
      ),
      'Host configurations'
    )
  },
  'CMA configuration',
  {
    isReverse: 'is_reverse',
    otelPrivateKey: 'otel_private_key',
    otelPublicCertificate: 'otel_public_certificate',
    otelCaCertificate: 'otel_ca_certificate'
  }
);

export const agentConfigurationDecoder = JsonDecoder.object<AgentConfiguration>(
  {
    name: JsonDecoder.string,
    connectionMode: JsonDecoder.string,
    type: JsonDecoder.enumeration<AgentType>(AgentType, 'Agent type'),
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
    configuration: JsonDecoder.oneOf<TelegrafConfiguration | CMAConfiguration>(
      [telegrafConfigurationDecoder, cmaConfigurationDecoder],
      'Agent configuration configuration'
    )
  },
  'Agent configuration',
  {
    connectionMode: 'connection_mode'
  }
);

export const tokenDecoder = JsonDecoder.object(
  {
    name: JsonDecoder.string,
    creator: JsonDecoder.object(
      {
        id: JsonDecoder.number,
        name: JsonDecoder.string
      },
      'Creator'
    )
  },
  'ListedToken'
).map(({ name, creator }) => {
  return {
    name,
    creatorId: creator.id,
    id: `${name}_${creator?.id}`,
    token_name: name
  };
});

export const listTokensDecoder = buildListingDecoder({
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Tokens',
  listingDecoderName: 'listTokens'
});
