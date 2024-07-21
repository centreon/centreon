import { ColumnType, Column } from '@centreon/ui';

import {
  labelActions,
  labelCreationDate,
  labelCreator,
  labelDescription,
  labelLastUpdate,
  labelName,
  labelType,
  labelUpdateBy
} from '../../translatedLabels';

import { Actions } from './Actions';

const getColumns = ({
  t
}): {
  columns: Array<Column>;
} => {
  const columns = [
    {
      disablePadding: false,
      getFormattedString: ({ name }) => name,
      id: 'name',
      label: t(labelName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ type }) => type,
      id: 'type',
      label: t(labelType),
      sortField: 'type',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ description }) => description,
      id: 'description',
      label: t(labelDescription),
      sortField: 'description',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ createdBy }): string => createdBy?.name,
      id: 'created_by',
      label: t(labelCreator),
      sortField: 'created_by',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ createdAt }): string => createdAt?.slice(0, 10),
      id: 'created_at',
      label: t(labelCreationDate),
      sortField: 'created_at',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ updatedBy }): string => updatedBy?.name,
      id: 'updated_by',
      label: t(labelUpdateBy),
      sortField: 'updated_by',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ updatedAt }): string => updatedAt?.slice(0, 10),
      id: 'updated_at',
      label: t(labelLastUpdate),
      sortField: 'updated_at',
      sortable: true,
      type: ColumnType.string
    },
    {
      Component: Actions,
      clickable: true,
      disablePadding: false,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    }
  ];

  return { columns };
};

export default getColumns;
