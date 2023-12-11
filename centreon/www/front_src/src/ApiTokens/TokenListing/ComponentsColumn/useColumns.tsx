import { useMemo } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Column, ColumnType, useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelActive, labelRevoked } from '../../translatedLabels';
import { selectedColumnIdsAtom } from '../atoms';

import ActionsColumn from './ActionsColumn';
import Information from './Information';
import { Columns, UseColumns, defaultSelectedColumnIds } from './models';

const dateFormat = 'L';

export const useColumns = (): UseColumns => {
  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const { timezone } = useAtomValue(userAtom);

  const onSelectColumns = (updatedColumnIds: Array<string>): void => {
    setSelectedColumnIds(updatedColumnIds);
  };

  const onResetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  const columns: Array<Column> = useMemo(() => {
    return [
      {
        Component: ({ row }) => (
          <Information
            data={row.is_revoked ? t(labelRevoked) : t(labelActive)}
          />
        ),
        id: 'status',
        label: Columns.Status,
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }) => {
          return <Information data={row.name} />;
        },
        id: 'token_name',
        label: Columns.Name,
        sortField: 'token_name',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }) => (
          <Information
            data={format({
              date: row.creation_date,
              formatString: dateFormat
            })}
          />
        ),
        id: 'creation_date',
        label: Columns.CreationDate,
        sortField: 'creation_date',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }) => (
          <Information
            data={format({
              date: row.expiration_date,
              formatString: dateFormat
            })}
          />
        ),
        id: 'expiration_date',
        label: Columns.ExpirationDate,
        sortField: 'expiration_date',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }) => <Information data={row.user.name} />,
        id: 'user_name',
        label: Columns.User,
        sortField: 'user.name',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }) => <Information data={row.creator.name} />,
        id: 'creator_name',
        label: Columns.Creator,
        sortField: 'creator.name',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ActionsColumn,
        id: 'actions',
        label: Columns.Actions,
        type: ColumnType.component
      }
    ];
  }, [timezone]);

  return { columns, onResetColumns, onSelectColumns, selectedColumnIds };
};
