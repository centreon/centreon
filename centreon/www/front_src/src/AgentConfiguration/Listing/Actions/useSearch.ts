import debounce from '@mui/utils/debounce';
import { useAtomValue, useSetAtom } from 'jotai';
import { ChangeEvent, useRef, useState } from 'react';
import { changeSearchAtom, searchAtom } from '../../atoms';

interface UseSearchState {
  search: string;
  change: (event: ChangeEvent) => void;
}

export const useSearch = (): UseSearchState => {
  const search = useAtomValue(searchAtom);
  const changeSearch = useSetAtom(changeSearchAtom);
  const [inputValue, setInputValue] = useState(search);

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((debouncedSearch): void => {
      changeSearch(debouncedSearch);
    }, 500)
  );

  const change = ({ target }): void => {
    setInputValue(target.value);
    searchDebounced.current(target.value);
  };

  return { search: inputValue, change };
};
