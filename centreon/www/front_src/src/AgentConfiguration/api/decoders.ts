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
            name: JsonDecoder.string
          },
          'poller'
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
    otelPrivateKey: JsonDecoder.string,
    otelPublicCertificate: JsonDecoder.string,
    otelCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
    confPrivateKey: JsonDecoder.string,
    confServerPort: JsonDecoder.number,
    confCertificate: JsonDecoder.string
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
    isReverse: JsonDecoder.boolean,
    otelPublicCertificate: JsonDecoder.string,
    otelCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
    otelPrivateKey: JsonDecoder.string,
    hosts: JsonDecoder.array(
      JsonDecoder.object(
        {
          address: JsonDecoder.string,
          port: JsonDecoder.number,
          pollerCaCertificate: JsonDecoder.nullable(JsonDecoder.string),
          pollerCaName: JsonDecoder.nullable(JsonDecoder.string)
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
  'Agent configuration'
);
