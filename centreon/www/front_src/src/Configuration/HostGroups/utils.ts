export const filtersInitialValues = {
  name: '',
  alias: '',
  enabled: false,
  disabled: false
};

export const defaultSelectedColumnIds = [
  'name',
  'alias',
  'enabled_hosts_count',
  'disabled_hosts_count',
  'actions',
  'is_activated'
];

export const truncateString = (str: string, maxLength = 50): string => {
  return str.length > maxLength ? `${str.slice(0, maxLength)}...` : str;
};
