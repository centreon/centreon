import { SetStateAction } from 'jotai';
import { ChangeEvent, Dispatch } from 'react';

interface State {
  change: (event) => void;
  value: string;
}

interface Props<TFilters> {
  name: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const useText = <TFilters>({
  name,
  filters,
  setFilters
}: Props<TFilters>): State => {
  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFilters({ ...filters, [name]: event.target.value });
  };

  return {
    change,
    value: filters[name]
  };
};

export default useText;
