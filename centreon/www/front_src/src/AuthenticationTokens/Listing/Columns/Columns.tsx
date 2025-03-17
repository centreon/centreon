import { useMemo } from 'react';

import { useAtom, useAtomValue } from 'jotai';

import {
  Column as ColumnTable,
  ColumnType,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';
import { selectedColumnIdsAtom } from '../atoms';

import { useTranslation } from 'react-i18next';
import ActionsColumn from './ActionsColumn';
import ExpirationDate from './ExpirationDate/ExpirationDate';
import Status from './Status/Status';
import { Column, ColumnId } from './models';

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
        sortField: 'token_name',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string => row?.type.toUpperCase(),
        id: ColumnId.Type,
        label: t(Column.Type),
        sortField: 'type',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string => row?.user.name,
        id: ColumnId.UserName,
        label: t(Column.User),
        sortField: 'user.name',
        sortable: true,
        type: ColumnType.string
      },
      {
        getFormattedString: (row): string => row.creator.name,
        id: ColumnId.CreatorName,
        label: t(Column.Creator),
        sortField: 'creator.name',
        sortable: true,
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
        sortField: 'creation_date',
        sortable: true,
        type: ColumnType.string
      },
      {
        Component: ExpirationDate,
        id: ColumnId.ExpirationDate,
        label: t(Column.ExpirationDate),
        sortField: 'expiration_date',
        sortable: true,
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
        type: ColumnType.component,
        sortField: 'is_revoked',
        sortable: true
      }
    ];
  }, [timezone]);

  return { columns, onResetColumns, onSelectColumns, selectedColumnIds };
};
