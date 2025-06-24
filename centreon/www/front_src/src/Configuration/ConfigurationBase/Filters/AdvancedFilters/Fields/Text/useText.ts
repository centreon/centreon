import { useAtom } from 'jotai';
import { ChangeEvent } from 'react';
import { filtersAtom } from '../../../../atoms';

interface Props {
  change: (event) => void;
  value: string;
}

const useText = ({ name }): Props => {
  const [filters, setFilters] = useAtom(filtersAtom);

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFilters({ ...filters, [name]: event.target.value });
  };

  return {
    change,
    value: filters[name]
  };
};

export default useText;
