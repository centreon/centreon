import debounce from '@mui/utils/debounce';
import { useAtom } from 'jotai';
import { ChangeEvent, useRef, useState } from 'react';
import { searchAtom } from '../../atoms';

interface UseSearchState {
  search: string;
  change: (event: ChangeEvent) => void;
}

export const useSearch = (): UseSearchState => {
  const [search, setSearch] = useAtom(searchAtom);
  const [inputValue, setInputValue] = useState(search);

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((debouncedSearch): void => {
      setSearch(debouncedSearch);
    }, 500)
  );

  const change = ({ target }): void => {
    setInputValue(target.value);
    searchDebounced.current(target.value);
  };

  return { search: inputValue, change };
};
