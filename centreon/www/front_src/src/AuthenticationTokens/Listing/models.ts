import { Duration } from '../Modal/models';
import { TokenType } from '../models';

export interface DataListing {
  isError: boolean;
  isLoading: boolean;
  limit?: number;
  page?: number;
  rows?: Array<Token>;
  total?: number;
}

export interface NamedEntity {
  id: number;
  name: string;
}

export interface Token {
  creationDate: string;
  creator: NamedEntity;
  expirationDate: string | null;
  isRevoked: boolean;
  name: string;
  user?: NamedEntity;
  type: TokenType;
}
export interface Row {
  row: Token;
}

export interface SortParams {
  sortField: string;
  sortOrder: string;
}

export interface CreateTokenFormValues {
  customizeDate: null | Date;
  duration: Omit<Duration, 'unit'> | null;
  tokenName: string;
  user: NamedEntity | null;
  type: NamedEntity;
}
