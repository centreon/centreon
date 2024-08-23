import { Column, ColumnType } from '@centreon/ui';

import {
  labelActions,
  labelChannels,
  labelName,
  labelPeriod,
  labelResources,
  labelStatus,
  labelUsers
} from '../../translatedLabels';
import { Actions, Activate } from '../Actions';
import { FormatChannels, formatResourcesForListing } from '../utils';

export const defaultQueryParams = {
  limit: 10,
  page: 1,
  search: {
    regex: {
      fields: ['name'],
      value: ''
    }
  },
  sort: { name: 'asc' },
  total: 56
};

export const fillNotifications = (numberOfRows: number): unknown => {
  return Array.from(Array(numberOfRows).keys()).map((index) => ({
    channels: ['Email'],
    id: index + 1,
    is_activated: !!(index % 2),
    name: `notification${index + 1}`,
    resources: [
      {
        count: Math.floor(Math.random() * 100),
        type: 'servicegroup'
      },
      {
        count: Math.floor(Math.random() * 100),
        type: 'hostgroup'
      },
      {
        count: Math.floor(Math.random() * 100),
        type: 'businessview'
      }
    ],
    timeperiod: {
      id: 1,
      name: '24h/24 - 7/7 days'
    },
    user_count: Math.floor(Math.random() * 100)
  }));
};

export const getListingResponse = ({
  page = 1,
  limit = 10,
  rows = 56
}: {
  limit?: number;
  page?: number;
  rows?: number;
}): object => {
  return {
    meta: {
      limit,
      page,
      search: {},
      sort_by: {},
      total: 56
    },
    result: fillNotifications(rows)
  };
};

export const getListingColumns = (): Array<Column> => [
  {
    disablePadding: false,
    getFormattedString: ({ name }): string => name,
    id: 'name',
    label: labelName,
    sortField: 'name',
    sortable: true,
    type: ColumnType.string
  },
  {
    disablePadding: false,
    getFormattedString: ({ userCount }): string => `${userCount} users`,
    id: 'userCount',
    label: labelUsers,
    sortField: 'users',
    sortable: true,
    type: ColumnType.string
  },
  {
    disablePadding: false,
    getFormattedString: ({ resources }): string =>
      formatResourcesForListing(resources),
    id: 'resources',
    label: labelResources,
    sortField: 'resources',
    sortable: true,
    type: ColumnType.string
  },
  {
    disablePadding: false,
    getFormattedString: ({ timeperiod }): string => timeperiod?.name,
    id: 'timeperiod',
    label: labelPeriod,
    type: ColumnType.string
  },
  {
    Component: FormatChannels,
    disablePadding: false,
    id: 'channels',
    label: labelChannels,
    type: ColumnType.component
  },
  {
    Component: Actions,
    clickable: true,
    disablePadding: true,
    id: 'actions',
    label: labelActions,
    type: ColumnType.component
  },
  {
    Component: Activate,
    clickable: true,
    disablePadding: false,
    id: 'isActivated',
    label: labelStatus,
    sortField: 'is_activated',
    sortable: true,
    type: ColumnType.component
  }
];

export const multipleNotificationsSuccessResponse = {
  results: [
    {
      href: '/configuration/notification/1',
      message: null,
      status: 204
    },
    {
      href: '/configuration/notification/2',
      message: null,
      status: 204
    },
    {
      href: '/configuration/notification/3',
      message: null,
      status: 204
    }
  ]
};

export const multipleNotificationsWarningResponse = {
  results: [
    {
      href: '/configuration/notification/1',
      message: 'not found',
      status: 404
    },
    {
      href: '/configuration/notification/2',
      message: 'internal server error',
      status: 500
    },
    {
      href: '/configuration/notification/3',
      message: null,
      status: 204
    }
  ]
};

export const multipleNotificationsfailedResponse = {
  results: [
    {
      href: '/configuration/notification/1',
      message: 'internal server error',
      status: 500
    },
    {
      href: '/configuration/notification/2',
      message: 'internal server error',
      status: 500
    },
    {
      href: '/configuration/notification/3',
      message: 'not found',
      status: 404
    }
  ]
};
