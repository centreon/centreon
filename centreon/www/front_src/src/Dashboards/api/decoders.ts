import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { DashboardRole, Dashboard, NamedEntity } from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

export const dashboardDecoderObject = {
  ...namedEntityDecoder,
  createdAt: JsonDecoder.string,
  createdBy: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Created By'),
  description: JsonDecoder.nullable(JsonDecoder.string),
  ownRole: JsonDecoder.enumeration<DashboardRole>(
    DashboardRole,
    'DashboardRole'
  ),
  updatedAt: JsonDecoder.string,
  updatedBy: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Updated By')
};

const dashboardDecoder = JsonDecoder.object<Dashboard>(
  dashboardDecoderObject,
  'Dashboard',
  {
    createdAt: 'created_at',
    createdBy: 'created_by',
    ownRole: 'own_role',
    updatedAt: 'updated_at',
    updatedBy: 'updated_by'
  }
);

export const dashboardListDecoder = buildListingDecoder({
  entityDecoder: dashboardDecoder,
  entityDecoderName: 'Dashboard Listing',
  listingDecoderName: 'Dashboards'
});
