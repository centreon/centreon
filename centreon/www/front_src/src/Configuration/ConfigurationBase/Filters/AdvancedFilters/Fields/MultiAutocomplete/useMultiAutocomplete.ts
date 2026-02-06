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
  const change = (_, items: Array<SelectEntry>): void => {
    const selectedItems = map(pick(['id', 'name']), items || []);

    setFilters({ ...filters, [name]: selectedItems });
  };

  const deleteItem =
    (name) =>
    (_, option): void => {
      const newItems = reject(propEq(option.id, 'id'), filters[name]);

      setFilters({
        ...filters,
        [name]: newItems
      });
    };

  const value = useMemo(() => {
    return filters?.[name]?.map((type) => ({
      ...type,
      name: type.name.replace('_', ' ')
    }));
  }, [filters?.[name]]);

  return {
    change,
    deleteItem,
    value
  };
};

export default useMultiAutocomplete;
