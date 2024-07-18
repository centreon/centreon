import { useMemo } from 'react';

import { useAtom, useAtomValue } from 'jotai';

import {
  Column as ColumnTable,
  ColumnType,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { selectedColumnIdsAtom } from '../atoms';
import { Row } from '../models';
import Title from '../Title';

import Activate from './Activate';
import ActionsColumn from './ActionsColumn';
import { Column, UseColumns, defaultSelectedColumnIds } from './models';

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

  const columns: Array<ColumnTable> = useMemo(() => {
    return [
      {
        Component: ({ row }: Row) => {
          return <Title msg={row.name} variant="body2" />;
        },
        id: 'token_name',
        label: Column.Name,
        sortField: 'token_name',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }: Row) => (
          <Title
            msg={format({
              date: row.creationDate,
              formatString: dateFormat
            })}
            variant="body2"
          />
        ),
        id: 'creation_date',
        label: Column.CreationDate,
        sortField: 'creation_date',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }: Row) => (
          <Title
            msg={format({
              date: row.expirationDate,
              formatString: dateFormat
            })}
            variant="body2"
          />
        ),
        id: 'expiration_date',
        label: Column.ExpirationDate,
        sortField: 'expiration_date',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }: Row) => (
          <Title msg={row.user.name} variant="body2" />
        ),
        id: 'user_name',
        label: Column.User,
        sortField: 'user.name',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ({ row }: Row) => (
          <Title msg={row.creator.name} variant="body2" />
        ),
        id: 'creator_name',
        label: Column.Creator,
        sortField: 'creator.name',
        sortable: true,
        type: ColumnType.component
      },
      {
        Component: ActionsColumn,
        id: 'actions',
        label: Column.Actions,
        type: ColumnType.component
      },
      {
        Component: Activate,
        id: 'activate',
        label: Column.Activate,
        type: ColumnType.component
      }
    ];
  }, [timezone]);

  return { columns, onResetColumns, onSelectColumns, selectedColumnIds };
};
