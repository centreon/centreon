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
    otelServerPort: JsonDecoder.number,
    otelCaCertificate: JsonDecoder.string,
    otelServerAddress: JsonDecoder.string,
    otelPublicCertificate: JsonDecoder.string,
    confPrivateKey: JsonDecoder.string,
    confServerPort: JsonDecoder.number,
    confCertificate: JsonDecoder.string
  },
  'Telegraf configuration',
  {
    otelPrivateKey: 'otel_private_key',
    otelServerPort: 'otel_server_port',
    otelServerAddress: 'otel_server_address',
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
    otlpCaCertificate: JsonDecoder.string,
    otlpCertificate: JsonDecoder.string,
    otlpPrivateKey: JsonDecoder.string,
    otlpReceiverAddress: JsonDecoder.string,
    otlpReceiverPort: JsonDecoder.number,
    hosts: JsonDecoder.array(
      JsonDecoder.object(
        {
          address: JsonDecoder.string,
          port: JsonDecoder.number,
          certificate: JsonDecoder.string,
          key: JsonDecoder.string
        },
        'Host configuration'
      ),
      'Host configurations'
    )
  },
  'CMA configuration',
  {
    isReverse: 'is_reverse',
    otlpPrivateKey: 'otlp_private_key',
    otlpCertificate: 'otlp_certificate',
    otlpCaCertificate: 'otlp_ca_certificate',
    otlpReceiverAddress: 'otlp_receiver_address',
    otlpReceiverPort: 'otlp_receiver_port'
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
