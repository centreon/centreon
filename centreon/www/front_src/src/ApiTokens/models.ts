export enum TokenType {
  API = 'api',
  CMA = 'cma'
}

export interface ModalState {
  isOpen: boolean;
  type?: TokenType;
  mode: 'add' | 'edit';
}
