export interface NamedEntity {
  id: number;
  name: string;
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
  users: Array<NamedEntity>;
  creators: Array<NamedEntity>;
  expirationDate: Date | null;
  creationDate: Date | null;
  enabled: boolean;
  disabled: boolean;
}
