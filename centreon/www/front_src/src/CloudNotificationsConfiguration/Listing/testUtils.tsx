import { ColumnType, Column } from '@centreon/ui';

import {
  labelName,
  labelChannels,
  labelUsers,
  labelResources,
  labelPeriod,
  labelActions,
  labelStatus
} from '../translatedLabels';

import { FormatChannels, formatResourcesForListing } from './utils';
import Actions from './Actions';
import ActionActivate from './Actions/ActivateAction';

export const fillNotifications = (numberOfRows: number): unknown => {
  return Array.from(Array(numberOfRows).keys()).map((index) => ({
    channels: ['Email'],
    id: index,
    is_activated: !!(index % 2),
    name: `notification${index}`,
    resources: [
      {
        count: Math.floor(Math.random() * 100),
        type: 'servicegroup'
      },
      {
        count: Math.floor(Math.random() * 100),
        type: 'hostgroup'
      }
    ],
    timeperiod: {
      id: 1,
      name: '24h'
    },
    user_count: Math.floor(Math.random() * 100)
  }));
};

export const getListingResponse = ({
  page = 1,
  limit = 10
}: {
  limit?: number;
  page?: number;
}): object => {
  return {
    meta: {
      limit,
      page,
      search: {},
      sort_by: {},
      total: 1
    },
    result: fillNotifications(56)
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
    Component: ActionActivate,
    clickable: true,
    disablePadding: false,
    id: 'isActivated',
    label: labelStatus,
    sortField: 'is_activated',
    sortable: true,
    type: ColumnType.component
  }
];

export const defaultQueryParams = {
  limit: 10,
  page: 1,
  search: {
    regex: {
      fields: ['name', 'resources', 'channels', 'users'],
      value: ''
    }
  },
  sort: { name: 'asc' },
  total: 0
};
