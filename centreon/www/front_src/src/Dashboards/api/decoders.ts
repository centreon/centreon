import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Dashboard } from '../models';

const dashboardDecoder = JsonDecoder.object<Dashboard>(
  {
    createdAt: JsonDecoder.string,
    description: JsonDecoder.nullable(JsonDecoder.string),
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    updatedAt: JsonDecoder.string
  },
  'Dashboard',
  {
    createdAt: 'created_at',
    updatedAt: 'updated_at'
  }
);

export const dashboardListDecoder = buildListingDecoder({
  entityDecoder: dashboardDecoder,
  entityDecoderName: 'Dashboard Listing',
  listingDecoderName: 'Dashboards'
});
