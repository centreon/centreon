/* eslint-disable typescript-sort-keys/string-enum */
import { Column } from '@centreon/ui';

export enum Columns {
  Actions = 'Actions',
  CreationDate = 'Creation Date',
  Creator = 'Creator',
  ExpirationDate = 'Expiration Date',
  Name = 'Name',
  Status = 'Status',
  User = 'User'
}

export enum selectableColumnsIds {
  Status = 'status',
  TokenName = 'token_name',
  CreationDate = 'creation_date',
  ExpirationDate = 'expiration_date',
  UserName = 'user_name',
  CreatorName = 'creator_name',
  Actions = 'actions'
}

export const defaultSelectedColumnIds: Array<string> =
  Object.values(selectableColumnsIds);

export interface UseColumns {
  columns: Array<Column>;
  onResetColumns: () => void;
  onSelectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
}
