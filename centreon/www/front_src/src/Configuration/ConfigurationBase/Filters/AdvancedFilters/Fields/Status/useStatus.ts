import { useAtom } from 'jotai';
import { ChangeEvent } from 'react';

import { filtersAtom } from '../../../../atoms';

interface UseStatus {
  change: (name: string) => (event: ChangeEvent<HTMLInputElement>) => void;
  valueEnable: boolean;
  valueDisable: boolean;
}

const useStatus = (): UseStatus => {
  const [filters, setFilters] = useAtom(filtersAtom);

  const change =
    (name: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      setFilters({ ...filters, [name]: event.target.checked });
    };

  return {
    valueEnable: filters.enabled,
    valueDisable: filters.disabled,
    change
  };
};

export default useStatus;
