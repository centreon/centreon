import { useAtom } from 'jotai';
import { equals, isNil, map, pick, propEq, reject } from 'ramda';

import { SelectEntry } from '@centreon/ui';
import { filtersAtom } from '../../../../atoms';

const useMultiAutocomplete = ({ name }) => {
  const [filters, setFilters] = useAtom(filtersAtom);

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
