import { Column, ColumnType, useLocaleDateTimeFormat } from '@centreon/ui';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelCreationDate,
  labelCreator,
  labelDescription,
  labelLastUpdate,
  labelName,
  labelType,
  labelUpdateBy
} from '../translatedLabels';
import Name from './Name';

const useColumns = (): {
  columns: Array<Column>;
} => {
  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  const columns = [
    {
      Component: Name,
      disablePadding: false,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.component
    },
    {
      disablePadding: false,
      getFormattedString: ({ type }) =>
        equals(type, 'vmware_v6') ? 'VMWare 6/7' : type,
      id: 'type',
      label: t(labelType),
      sortable: true,
      sortField: 'type',
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
      getFormattedString: ({ createdBy }): string => createdBy?.name || '',
      id: 'created_by',
      label: t(labelCreator),
      sortable: true,
      sortField: 'created_by',
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
      sortable: true,
      sortField: 'created_at',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ updatedBy }): string => updatedBy?.name || '',
      id: 'updated_by',
      label: t(labelUpdateBy),
      sortable: true,
      sortField: 'updated_by',
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
      sortable: true,
      sortField: 'updated_at',
      type: ColumnType.string
    }
  ];

  return { columns };
};

export default useColumns;
