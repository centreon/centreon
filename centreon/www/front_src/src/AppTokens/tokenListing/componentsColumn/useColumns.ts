import { useMemo } from 'react';

import { useAtom, useAtomValue } from 'jotai';

import { Column, ColumnType, useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelActive, labelRevoked } from '../../translatedLabels';
import { selectedColumnIdsAtom } from '../atoms';

import ActionsColumn from './ActionsColumn';
import { Columns, UseColumns, defaultSelectedColumnIds } from './models';

const dateFormat = 'L';

export const useColumns = (): UseColumns => {
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

  const columns: Array<Column> = useMemo(
    () => [
      {
        getFormattedString: ({ is_revoked }) =>
          is_revoked ? labelRevoked : labelActive,
        id: 'status',
        label: Columns.status,
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: ({ name }) => name,
        id: 'token_name',
        label: Columns.name,
        sortField: 'token_name',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: ({ creation_date }) => {
          return format({
            date: creation_date,
            formatString: dateFormat
          });
        },
        id: 'creation_date',
        label: Columns.creationDate,
        sortField: 'creation_date',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: ({ expiration_date }) => {
          return format({
            date: expiration_date,
            formatString: dateFormat
          });
        },
        id: 'expiration_date',
        label: Columns.expirationDate,
        sortField: 'expiration_date',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: ({ user }) => user.name,
        id: 'user_name',
        label: Columns.user,
        sortField: 'user.name',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: ({ creator }) => creator.name,
        id: 'creator_name',
        label: Columns.creator,
        sortField: 'creator.name',
        sortable: true,
        type: ColumnType.string
      },
      {
        Component: ActionsColumn,
        id: 'actions',
        label: Columns.actions,
        type: ColumnType.component
      }
    ],
    [timezone]
  );

  return { columns, onResetColumns, onSelectColumns, selectedColumnIds };
};
