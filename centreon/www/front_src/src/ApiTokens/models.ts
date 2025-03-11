export enum TokenType {
  API = 'api',
  CMA = 'centreon-monitoring-agent'
}

export interface ModalState {
  isOpen: boolean;
  type?: TokenType;
  mode: 'add' | 'edit';
}
