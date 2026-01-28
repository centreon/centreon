import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { metricsResourceDecoder } from '../../../../decoders';
import { MetaServiceMetric } from '../models';

const metaServiceMetricDecoder = JsonDecoder.object<MetaServiceMetric>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    resource: metricsResourceDecoder,
    unit: JsonDecoder.string,
    value: JsonDecoder.number
  },
  'MetaServiceMetric'
);

const metaServiceMetricListingDecoder = buildListingDecoder<MetaServiceMetric>({
  entityDecoder: metaServiceMetricDecoder,
  entityDecoderName: 'MetaServiceMetric',
  listingDecoderName: 'MetaServiceMetricListing'
});

export { metaServiceMetricDecoder, metaServiceMetricListingDecoder };
