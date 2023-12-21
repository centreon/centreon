import { Column as ColumnTable } from '@centreon/ui';

export enum Column {
  Actions = 'Actions',
  CreationDate = 'Creation Date',
  Creator = 'Creator',
  ExpirationDate = 'Expiration Date',
  Name = 'Name',
  Status = 'Status',
  User = 'User'
}

export enum SelectableColumnsIds {
  Actions = 'actions',
  CreationDate = 'creation_date',
  CreatorName = 'creator_name',
  ExpirationDate = 'expiration_date',
  Status = 'status',
  TokenName = 'token_name',
  UserName = 'user_name'
}

export const defaultSelectedColumnIds: Array<string> = [
  SelectableColumnsIds.Status,
  SelectableColumnsIds.TokenName,
  SelectableColumnsIds.CreationDate,
  SelectableColumnsIds.ExpirationDate,
  SelectableColumnsIds.UserName,
  SelectableColumnsIds.CreatorName,
  SelectableColumnsIds.Actions
];

export interface UseColumns {
  columns: Array<ColumnTable>;
  onResetColumns: () => void;
  onSelectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
}
