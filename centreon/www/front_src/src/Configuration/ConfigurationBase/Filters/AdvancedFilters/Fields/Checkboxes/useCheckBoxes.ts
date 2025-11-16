import { SetStateAction } from 'jotai';
import { equals, includes, reject } from 'ramda';
import { ChangeEvent, Dispatch } from 'react';

interface UseCheckBoxes {
  change: (event: ChangeEvent<HTMLInputElement>) => void;
  isChecked: (id: string) => boolean;
}

interface Props<TFilters> {
  name: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}
const useCheckBoxes = <TFilters>({
  filters,
  setFilters,
  name
}: Props<TFilters>): UseCheckBoxes => {
  const isChecked = (id: string): boolean => includes(id, filters[name] || []);

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    const valueId = event.target.name;

    if (!includes(valueId, filters[name])) {
      setFilters({ ...filters, [name]: [...filters[name], valueId] });

      return;
    }

    const filteredOptions = reject(equals(valueId), filters[name]);

    setFilters({ ...filters, [name]: filteredOptions });
  };

  return {
    isChecked,
    change
  };
};

export default useCheckBoxes;
