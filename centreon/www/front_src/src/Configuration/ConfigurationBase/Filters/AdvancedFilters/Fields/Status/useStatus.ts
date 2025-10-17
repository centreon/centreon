import { SetStateAction } from 'jotai';
import { ChangeEvent, Dispatch } from 'react';

interface UseStatus {
  change: (name: string) => (event: ChangeEvent<HTMLInputElement>) => void;
  valueEnable: boolean;
  valueDisable: boolean;
}

interface Props<TFilters> {
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}
const useStatus = <TFilters>({
  filters,
  setFilters
}: Props<TFilters>): UseStatus => {
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
