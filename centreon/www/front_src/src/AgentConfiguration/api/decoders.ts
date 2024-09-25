import { buildListingDecoder } from '@centreon/ui';
import { JsonDecoder } from 'ts.data.json';
import {
  AgentConfiguration,
  AgentConfigurationConfiguration,
  AgentType
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
    configuration: JsonDecoder.object<AgentConfigurationConfiguration>(
      {
        otelPrivateKey: JsonDecoder.string,
        otelCaCertificate: JsonDecoder.string,
        otelPublicCertificate: JsonDecoder.string,
        confPrivateKey: JsonDecoder.string,
        confServerPort: JsonDecoder.number,
        confCertificate: JsonDecoder.string
      },
      'Agent configuration configuration',
      {
        otelPrivateKey: 'otel_private_key',
        otelCaCertificate: 'otel_ca_certificate',
        otelPublicCertificate: 'otel_public_certificate',
        confCertificate: 'conf_certificate',
        confPrivateKey: 'conf_private_key',
        confServerPort: 'conf_server_port'
      }
    )
  },
  'Agent configuration'
);
