import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { NamedEntity, AdditionalConnectors } from '../Listing/models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const dashboardDecoder = JsonDecoder.object<AdditionalConnectors>(
  {
    ...namedEntityDecoder,
    createdAt: JsonDecoder.string,
    createdBy: JsonDecoder.nullable(
      JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Created By')
    ),
    description: JsonDecoder.nullable(JsonDecoder.string),
    type: JsonDecoder.string,
    updatedAt: JsonDecoder.string,
    updatedBy: JsonDecoder.nullable(
      JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Updated By')
    )
  },
  'Additional connector',
  {
    createdAt: 'created_at',
    createdBy: 'created_by',
    updatedAt: 'updated_at',
    updatedBy: 'updated_by'
  }
);

export const dashboardDecoderListDecoder = buildListingDecoder({
  entityDecoder: dashboardDecoder,
  entityDecoderName: 'Additional connector',
  listingDecoderName: 'Additional connectors List'
});
