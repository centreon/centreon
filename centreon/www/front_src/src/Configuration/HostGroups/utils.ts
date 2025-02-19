import { ModalState } from '../ConfigurationBase/Modal/models';

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'enabled_hosts_count',
  'disabled_hosts_count',
  'actions',
  'is_activated'
];

export const filtersInitialValues = {
  name: '',
  alias: '',
  enabled: false,
  disabled: false
};

export const modalInitialState: ModalState = {
  isOpen: false,
  mode: 'edit',
  id: null
};
