/* eslint-disable typescript-sort-keys/interface */
// TODO merge cleanup

import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import {
  Dashboard,
  DashboardPanel,
  DashboardRole,
  NamedEntity
} from './models';
import {
  DashboardShare,
  ContactType
} from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

/**
 * dashboard property : panel
 */

const dashboardPanelDecoder = JsonDecoder.object<DashboardPanel>(
  {
    ...namedEntityDecoder,
    layout: JsonDecoder.object<DashboardPanel['layout']>(
      {
        height: JsonDecoder.number,
        minHeight: JsonDecoder.number,
        minWidth: JsonDecoder.number,
        width: JsonDecoder.number,
        x: JsonDecoder.number,
        y: JsonDecoder.number
      },
      'Dashboard panel layout',
      {
        minHeight: 'min_height',
        minWidth: 'min_width'
      }
    ),
    widgetSettings: JsonDecoder.succeed,
    widgetType: JsonDecoder.string
  },
  'Dashboard panel',
  {
    widgetSettings: 'widget_settings',
    widgetType: 'widget_type'
  }
);

/**
 * dashboard entity
 */

export const dashboardEntityDecoder = {
  ...namedEntityDecoder,
  createdAt: JsonDecoder.string,
  createdBy: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Created By'),
  description: JsonDecoder.nullable(JsonDecoder.string),
  ownRole: JsonDecoder.enumeration<DashboardRole>(
    DashboardRole,
    'Dashboard role'
  ),
  panels: JsonDecoder.optional(
    JsonDecoder.array(dashboardPanelDecoder, 'Panels')
  ),
  updatedAt: JsonDecoder.string,
  updatedBy: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Updated By')
};

export const dashboardDecoder = JsonDecoder.object<Dashboard>(
  dashboardEntityDecoder,
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

const dashboardShareDecoder = JsonDecoder.object<DashboardShare>(
  {
    email: JsonDecoder.nullable(JsonDecoder.string),
    fullname: JsonDecoder.nullable(JsonDecoder.string),
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    role: JsonDecoder.enumeration<DashboardRole>(
      DashboardRole,
      'DashboardRole'
    ),
    type: JsonDecoder.enumeration<ContactType>(ContactType, 'ContactType')
  },
  'Dashboard Share'
);

export const dashboardShareListDecoder = buildListingDecoder({
  entityDecoder: dashboardShareDecoder,
  entityDecoderName: 'Dashboard share listing',
  listingDecoderName: 'Dashboard shares'
});
