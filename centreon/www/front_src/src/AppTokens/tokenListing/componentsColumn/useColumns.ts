import { useAtom } from 'jotai';

import { Column, ColumnType } from '@centreon/ui';

import { selectedColumnIdsAtom } from '../atoms';

import ActionsColumn from './ActionsColumn';
import StatusColumn from './StatusColumn';
import UserColumn from './UserColumn';
import { Columns, UseColumns, defaultSelectedColumnIds } from './models';

const columns: Array<Column> = [
  {
    Component: StatusColumn,
    id: 'status',
    label: Columns.status,
    type: ColumnType.component
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
    getFormattedString: ({ creation_date }) => creation_date,
    id: 'creation_date',
    label: Columns.creationDate,
    sortField: 'creation_date',
    sortable: true,
    type: ColumnType.string
  },
  {
    getFormattedString: ({ expiration_date }) => expiration_date,
    id: 'expiration_date',
    label: Columns.expirationDate,
    sortField: 'expiration_date',
    sortable: true,
    type: ColumnType.string
  },
  {
    Component: UserColumn,
    id: 'user_name',
    label: Columns.user,
    sortField: 'user.name',
    sortable: true,
    type: ColumnType.component
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
];

export const useColumns = (): UseColumns => {
  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const onSelectColumns = (updatedColumnIds: Array<string>): void => {
    setSelectedColumnIds(updatedColumnIds);
  };

  const onResetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  return { columns, onResetColumns, onSelectColumns, selectedColumnIds };
};
