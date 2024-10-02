import { useAtom } from 'jotai';
import { ChangeEvent, useCallback, useRef } from 'react';
import { searchAtom } from '../atoms';

interface UseSearchState {
  search: string;
  change: (event: ChangeEvent) => void;
}

export const useSearch = (): UseSearchState => {
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);

  const [search, setSearch] = useAtom(searchAtom);

  const change = useCallback((event: ChangeEvent): void => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => setSearch(event.target.value), 500);
  }, []);

  return { search, change };
};
