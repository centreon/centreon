export const filtersDefaultValue = {
  name: '',
  alias: '',
  enabled: false,
  disabled: false
};

export const truncateString = (str: string, maxLength = 50): string => {
  return str.length > maxLength ? `${str.slice(0, maxLength)}...` : str;
};
