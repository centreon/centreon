import { buildListingDecoder } from '@centreon/ui';
import { JsonDecoder } from 'ts.data.json';
import { AgentType } from '../models';

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
