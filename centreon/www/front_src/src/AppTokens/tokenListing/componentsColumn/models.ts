/* eslint-disable typescript-sort-keys/string-enum */
import { Column } from '@centreon/ui';

export enum Columns {
  actions = 'Actions',
  creationDate = 'Creation Date',
  creator = 'Creator',
  expirationDate = 'Expiration Date',
  name = 'Name',
  status = 'Status',
  user = 'User'
}

export enum selectableColumnsIds {
  status = 'status',
  tokenName = 'token_name',
  creationDate = 'creation_date',
  expirationDate = 'expiration_date',
  userName = 'user_name',
  creatorName = 'creator_name',
  actions = 'actions'
}

export const defaultSelectedColumnIds: Array<string> =
  Object.values(selectableColumnsIds);

export interface UseColumns {
  columns: Array<Column>;
  onResetColumns: () => void;
  onSelectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
}
