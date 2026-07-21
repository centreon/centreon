import { SetStateAction } from 'jotai';
import { equals, includes, reject } from 'ramda';
import { ChangeEvent, Dispatch } from 'react';

interface UseCheckboxes {
  change: (event: ChangeEvent<HTMLInputElement>) => void;
  isChecked: (id: string) => boolean;
}

interface Props<TFilters> {
  name: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}
const useCheckboxes = <TFilters>({
  filters,
  setFilters,
  name
}: Props<TFilters>): UseCheckboxes => {
  const filtersRecord = filters as Record<string, unknown>;

  const isChecked = (id: string): boolean =>
    includes(id, (filtersRecord[name] as Array<string>) || []);

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    const valueId = event.target.name;

    if (!includes(valueId, filtersRecord[name] as Array<string>)) {
      setFilters({
        ...filters,
        [name]: [...(filtersRecord[name] as Array<string>), valueId]
      });

      return;
    }

    const filteredOptions = reject(
      equals(valueId),
      filtersRecord[name] as Array<string>
    );

    setFilters({ ...filters, [name]: filteredOptions });
  };

  return {
    change,
    isChecked
  };
};

export default useCheckboxes;
