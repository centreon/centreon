import { Column as ColumnTable } from '@centreon/ui';

export enum Column {
  Actions = 'Actions',
  Activate = 'Activate/Revoke',
  CreationDate = 'Creation Date',
  Creator = 'Creator',
  ExpirationDate = 'Expiration Date',
  Name = 'Name',
  User = 'User'
}

export enum SelectableColumnsIds {
  Actions = 'actions',
  Activate = 'activate',
  CreationDate = 'creation_date',
  CreatorName = 'creator_name',
  ExpirationDate = 'expiration_date',
  TokenName = 'token_name',
  UserName = 'user_name'
}

export const defaultSelectedColumnIds: Array<string> = [
  SelectableColumnsIds.TokenName,
  SelectableColumnsIds.CreationDate,
  SelectableColumnsIds.ExpirationDate,
  SelectableColumnsIds.UserName,
  SelectableColumnsIds.CreatorName,
  SelectableColumnsIds.Actions,
  SelectableColumnsIds.Activate
];

export interface UseColumns {
  columns: Array<ColumnTable>;
  onResetColumns: () => void;
  onSelectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
}
