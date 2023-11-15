import { Column, ColumnType } from '@centreon/ui';

import { Columns } from './models';
import StatusColumn from './componentsColumn/StatusColumn';
import UserColumn from './componentsColumn/UserColumn';
import ActionsColumn from './componentsColumn/ActionsColumn';

export const useColumns = (): Array<Column> => {
  const columns = [
    {
      Component: StatusColumn,
      id: 'status',
      label: Columns.status,
      type: ColumnType.component
    },
    {
      getFormattedString: ({ name }) => name,
      id: 'name',
      label: Columns.name,
      type: ColumnType.string
    },
    {
      getFormattedString: ({ creation_date }) => creation_date,
      id: 'creationDate',
      label: Columns.creationDate,
      type: ColumnType.string
    },
    {
      getFormattedString: ({ expiration_date }) => expiration_date,
      id: 'expirationDate',
      label: Columns.expirationDate,
      type: ColumnType.string
    },
    {
      Component: UserColumn,
      id: 'user',
      label: Columns.user,
      type: ColumnType.component
    },
    {
      getFormattedString: ({ creator }) => creator.name,
      id: 'creator',
      label: Columns.creator,
      type: ColumnType.string
    },
    {
      Component: ActionsColumn,
      id: 'actions',
      label: Columns.actions,
      type: ColumnType.component
    }
  ];

  return columns;
};
