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

  const columns: Array<Column> = [
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
      getFormattedString: ({ type }: Record<string, unknown>) =>
        equals(type, 'vmware_v6') ? 'VMWare 6/7' : (type as string),
      id: 'type',
      label: t(labelType),
      sortable: true,
      sortField: 'type',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ description }: Record<string, unknown>) =>
        description as string,
      id: 'description',
      label: t(labelDescription),
      sortField: 'description',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ createdBy }: Record<string, unknown>): string =>
        (createdBy as { name?: string })?.name || '',
      id: 'created_by',
      label: t(labelCreator),
      sortable: true,
      sortField: 'created_by',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ createdAt }: Record<string, unknown>): string =>
        format({
          date: createdAt as string,
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
      getFormattedString: ({ updatedBy }: Record<string, unknown>): string =>
        (updatedBy as { name?: string })?.name || '',
      id: 'updated_by',
      label: t(labelUpdateBy),
      sortable: true,
      sortField: 'updated_by',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ updatedAt }: Record<string, unknown>): string =>
        updatedAt
          ? format({
              date: updatedAt as string,
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
