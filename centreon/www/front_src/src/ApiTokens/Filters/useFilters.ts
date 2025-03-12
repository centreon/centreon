import { equals, isNil, map, pick, propEq, reject } from 'ramda';

import { SelectEntry } from '@centreon/ui';

import { Filter, NamedEntity } from '../models';

import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { filtersAtom } from '../atoms';
import { filtersInitialValues } from '../utils';

interface UseFiltersState {
  isClearDisabled: boolean;
  changeName: (event) => void;
  changeTypes: (_, types: Array<SelectEntry>) => void;
  changeUser: (_, values) => void;
  changeCreator: (_, values) => void;
  filterCreators: (options) => Array<NamedEntity>;
  deleteCreator: (_, item) => void;
  deleteUser: (_, item) => void;
  deleteType: (_, item) => void;
  isOptionEqualToValue: (option, selectedValue) => boolean;
  handleSearch: () => void;
  reset: () => void;
  filters: Filter;
}

const useFilters = (): UseFiltersState => {
  const queryClient = useQueryClient();

  const [filters, setFilters] = useAtom(filtersAtom);

  const isClearDisabled = equals(filters, filtersInitialValues);

  const changeName = (event): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const changeTypes = (_, types: Array<SelectEntry>): void => {
    const selectedTypes = map(
      pick(['id', 'name']),
      types || []
    ) as Array<NamedEntity>;

    setFilters({ ...filters, types: selectedTypes });
  };

  const changeUser = (_, values): void => {
    const users = map(pick(['id', 'name']), values);
    setFilters({ ...filters, users });
  };

  const changeCreator = (_, values): void => {
    const creators = map(pick(['id', 'name']), values);
    setFilters({ ...filters, creators });
  };

  const filterCreators = (options): Array<NamedEntity> => {
    const creatorsData = options?.map(({ creator }) => creator);

    return getUniqData(creatorsData);
  };

  const deleteCreator = (_, item): void => {
    const creators = reject(
      ({ name }) => equals(item.name, name),
      filters.creators
    );

    setFilters({ ...filters, creators });
  };

  const deleteUser = (_, item): void => {
    const users = reject(({ name }) => equals(item.name, name), filters.users);

    setFilters({ ...filters, users });
  };

  const deleteType = (_, option): void => {
    const newItems = reject(propEq(option.id, 'id'), filters.types);

    setFilters({
      ...filters,
      types: newItems
    });
  };

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  const handleSearch = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listTokens'] });
  };

  const reset = (): void => {
    setFilters(filtersInitialValues);

    queryClient.invalidateQueries({ queryKey: ['listTokens'] });
  };

  return {
    isClearDisabled,
    changeName,
    changeTypes,
    changeUser,
    changeCreator,
    filterCreators,
    deleteCreator,
    deleteUser,
    deleteType,
    isOptionEqualToValue,
    handleSearch,
    reset,
    filters
  };
};

export default useFilters;
