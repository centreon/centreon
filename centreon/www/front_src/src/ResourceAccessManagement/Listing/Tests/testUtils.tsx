import { Column, ColumnType } from '@centreon/ui';

import {
  labelActions,
  labelDescription,
  labelName,
  labelStatus
} from '../../translatedLabels';
import { Actions, Activate } from '../Actions';

export const defaultQueryParams = {
  limit: 10,
  page: 1,
  search: {
    regex: {
      fields: ['name', 'description'],
      value: ''
    }
  },
  sort: { name: 'asc' },
  total: 64
};

export const fillResourceAccessRules = (numberOfRows: number): unknown => {
  return Array.from(Array(numberOfRows).keys()).map((index) => ({
    description: `resourceAccessRule${index + 1}`,
    id: index + 1,
    is_enabled: !!(index % 2),
    name: `rule${index}`
  }));
};

export const getListingResponse = ({
  page = 1,
  limit = 10,
  rows = 64
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
      total: 64
    },
    result: fillResourceAccessRules(rows)
  };
};

export const getListingColumns = (): Array<Column> => {
  return [
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
      getFormattedString: ({ description }): string => description,
      id: 'description',
      label: labelDescription,
      sortable: true,
      type: ColumnType.string
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
      disablePadding: true,
      id: 'isEnabled',
      label: labelStatus,
      type: ColumnType.component
    }
  ];
};
