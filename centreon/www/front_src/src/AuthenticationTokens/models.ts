export interface NamedEntity {
  id: number;
  name: string;
}

export interface User {
  id: number;
  alias: string;
}

export enum TokenType {
  API = 'api',
  CMA = 'cma'
}

export interface ModalState {
  isOpen: boolean;
  type?: TokenType;
  mode: 'add' | 'edit';
}

export interface Filter {
  name: string;
  types: Array<NamedEntity>;
  users: Array<User>;
  creators: Array<NamedEntity>;
  expirationDate: Date | null;
  creationDate: Date | null;
  enabled: boolean;
  disabled: boolean;
}
