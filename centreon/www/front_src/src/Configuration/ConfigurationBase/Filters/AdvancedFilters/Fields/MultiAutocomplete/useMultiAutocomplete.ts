import { map, pick, propEq, reject } from 'ramda';
import { useMemo } from 'react';

import { SelectEntry } from '@centreon/ui';
import { useAtom } from 'jotai';
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

  const value = useMemo(() => {
    return filters?.[name]?.map((type) => ({
      ...type,
      name: type.name.replace('_', ' ')
    }));
  }, [filters?.[name]]);

  return {
    value,
    deleteItem,
    change
  };
};

export default useMultiAutocomplete;
