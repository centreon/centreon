import { ChangeEvent } from 'react';

interface UseStatus {
  change: (name: string) => (event: ChangeEvent<HTMLInputElement>) => void;
  valueEnable: boolean;
  valueDisable: boolean;
}

const useStatus = ({ filters, setFilters }): UseStatus => {
  const change =
    (name: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      setFilters({ ...filters, [name]: event.target.checked });
    };

  return {
    valueEnable: filters.enabled,
    valueDisable: filters.disabled,
    change
  };
};

export default useStatus;
