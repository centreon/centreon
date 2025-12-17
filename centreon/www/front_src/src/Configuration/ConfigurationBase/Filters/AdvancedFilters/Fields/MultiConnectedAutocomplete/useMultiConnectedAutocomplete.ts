import { SelectEntry } from '@centreon/ui';

import { SetStateAction } from 'jotai';
import { equals, isNil, map, pick, propEq, reject } from 'ramda';
import { Dispatch } from 'react';

interface Props<TFilters> {
  name: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const useMultiAutocomplete = <TFilters>({
  name,
  setFilters,
  filters
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

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return (
      !isNil(option) &&
      equals(
        option.name.toString(),
        selectedValue.name.toString().replace('_', ' ')
      )
    );
  };

  return {
    isOptionEqualToValue,
    deleteItem,
    change,
    value: filters?.[name]
  };
};

export default useMultiAutocomplete;
