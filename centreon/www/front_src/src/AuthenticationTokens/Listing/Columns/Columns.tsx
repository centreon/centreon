import {
  Column as ColumnTable,
  ColumnType,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { useAtom, useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { selectedColumnIdsAtom } from '../atoms';
import ActionsColumn from './ActionsColumn';
import ExpirationDate from './ExpirationDate/ExpirationDate';
import { Column, ColumnId } from './models';
import Status from './Status/Status';

const dateFormat = 'L';

export const defaultSelectedColumnIds: Array<ColumnId> = [
  ColumnId.TokenName,
  ColumnId.Type,
  ColumnId.UserName,
  ColumnId.CreatorName,
  ColumnId.CreationDate,
  ColumnId.ExpirationDate,
  ColumnId.Actions,
  ColumnId.Activate
];

export interface UseColumnsState {
  columns: Array<ColumnTable>;
  onResetColumns: () => void;
  onSelectColumns: (updatedColumnIds: Array<ColumnId>) => void;
  selectedColumnIds: Array<string>;
}

export const useColumns = (): UseColumnsState => {
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { timezone } = useAtomValue(userAtom);
  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const onSelectColumns = (updatedColumnIds: Array<ColumnId>): void => {
    setSelectedColumnIds(updatedColumnIds);
  };

  const onResetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  const columns: Array<ColumnTable> = useMemo(() => {
    return [
      {
        getFormattedString: (row): string => row.name,
        id: ColumnId.TokenName,
        label: t(Column.Name),
        sortable: true,
        sortField: 'token_name',
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string => row?.type.toUpperCase(),
        id: ColumnId.Type,
        label: t(Column.Type),
        sortable: true,
        sortField: 'type',
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string => row?.user?.name || '-',
        id: ColumnId.UserName,
        label: t(Column.User),
        sortable: true,
        sortField: 'user.name',
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string => row.creator.name,
        id: ColumnId.CreatorName,
        label: t(Column.Creator),
        sortable: true,
        sortField: 'creator.name',
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string =>
          format({
            date: row.creationDate,
            formatString: dateFormat
          }),
        id: ColumnId.CreationDate,
        label: t(Column.CreationDate),
        sortable: true,
        sortField: 'creation_date',
        type: ColumnType.string
      },
      {
        Component: ExpirationDate,
        id: ColumnId.ExpirationDate,
        label: t(Column.ExpirationDate),
        sortable: true,
        sortField: 'expiration_date',
        type: ColumnType.component
      },
      {
        Component: ActionsColumn,
        id: ColumnId.Actions,
        label: t(Column.Actions),
        type: ColumnType.component
      },
      {
        Component: Status,
        id: ColumnId.Activate,
        label: t(Column.Activate),
        sortable: true,
        sortField: 'is_revoked',
        type: ColumnType.component
      }
    ];
  }, [timezone]);

  return { columns, onResetColumns, onSelectColumns, selectedColumnIds };
};
