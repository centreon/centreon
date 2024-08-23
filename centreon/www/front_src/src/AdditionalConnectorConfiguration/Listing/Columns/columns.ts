import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Column, ColumnType, useLocaleDateTimeFormat } from '@centreon/ui';

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

const useColumns = (): {
  columns: Array<Column>;
} => {
  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

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
      getFormattedString: ({ type }) =>
        equals(type, 'vmware_v6') ? 'VMWare 6/7' : type,
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
      getFormattedString: ({ createdAt }): string =>
        format({
          date: createdAt,
          formatString: 'L'
        }),
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
      getFormattedString: ({ updatedAt }): string =>
        updatedAt
          ? format({
              date: updatedAt,
              formatString: 'L'
            })
          : '',
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

export default useColumns;
