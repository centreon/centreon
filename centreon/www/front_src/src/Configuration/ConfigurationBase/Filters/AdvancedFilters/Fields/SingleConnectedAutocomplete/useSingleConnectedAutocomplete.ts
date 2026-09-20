import { SelectEntry } from '@centreon/ui';

import { SetStateAction } from 'jotai';
import { equals, is, isNil, pick } from 'ramda';
import { Dispatch, SyntheticEvent } from 'react';


type AutocompleteChangedValue =
  | SelectEntry
  | Array<SelectEntry | string>
  | string
  | null;

interface Props<TFilters> {
  name: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

interface UseSingleConnectedAutocompleteState {
  change: (event: SyntheticEvent, item: AutocompleteChangedValue) => void;
  isOptionEqualToValue: (
    option: SelectEntry,
    selectedValue: SelectEntry
  ) => boolean;
  value: SelectEntry | null;
}

const useSingleConnectedAutocomplete = <TFilters>({
  name,
  setFilters,
  filters
}: Props<TFilters>): UseSingleConnectedAutocompleteState => {
  const change = (_: SyntheticEvent, item: AutocompleteChangedValue): void => {
    const isSelectEntry =
      !isNil(item) && !Array.isArray(item) && !is(String, item);

    const selectedItem = isSelectEntry ? pick(['id', 'name'], item) : null;

    setFilters({ ...filters, [name]: selectedItem } as TFilters);
  };

  const isOptionEqualToValue = (
    option: SelectEntry,
    selectedValue: SelectEntry
  ): boolean => {
    return (
      !isNil(option) &&
      !isNil(selectedValue) &&
      equals(
        option.name.toString(),
        selectedValue.name.toString().replace('_', ' ')
      )
    );
  };

  const value = (filters as Record<string, unknown>)?.[name] as
    | SelectEntry
    | null
    | undefined;

  return {
    change,
    isOptionEqualToValue,
    value: value ?? null
  };
};

export default useSingleConnectedAutocomplete;
