import { SelectEntry } from '@centreon/ui';

import { SetStateAction } from 'jotai';
import { map, pick, propEq, reject } from 'ramda';
import { Dispatch, useMemo } from 'react';

interface Props<TFilters> {
  name: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const useMultiAutocomplete = <TFilters>({
  name,
  filters,
  setFilters
}: Props<TFilters>) => {
  const filtersRecord = filters as Record<string, unknown>;

  const change = (_: unknown, items: Array<SelectEntry>): void => {
    const selectedItems = map(pick(['id', 'name']), items || []);

    setFilters({ ...filters, [name]: selectedItems });
  };

  const deleteItem =
    (name: string) =>
    (_: unknown, option: SelectEntry): void => {
      const newItems = reject(
        propEq(option.id, 'id'),
        (filtersRecord[name] as Array<SelectEntry>) || []
      );

      setFilters({
        ...filters,
        [name]: newItems
      });
    };

  const value = useMemo(() => {
    return (filtersRecord?.[name] as Array<SelectEntry> | undefined)?.map(
      (type) => ({
        ...type,
        name: String(type.name).replace('_', ' ')
      })
    );
  }, [filtersRecord?.[name]]);

  return {
    change,
    deleteItem,
    value
  };
};

export default useMultiAutocomplete;
