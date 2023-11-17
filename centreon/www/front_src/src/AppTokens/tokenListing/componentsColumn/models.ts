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
  creationDate = 'creation_date',
  creatorName = 'creator_name',
  expirationDate = 'expiration_date',
  status = 'status',
  tokenName = 'token_name',
  userName = 'user_name'
}

export const defaultSelectedColumnIds: Array<string> =
  Object.values(selectableColumnsIds);

export interface UseColumns {
  columns: Array<Column>;
  onSelectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
}
