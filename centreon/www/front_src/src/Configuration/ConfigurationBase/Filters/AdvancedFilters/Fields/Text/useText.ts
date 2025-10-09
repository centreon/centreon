import { ChangeEvent } from 'react';

interface Props {
  change: (event) => void;
  value: string;
}

const useText = ({ name, filters, setFilters }): Props => {
  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFilters({ ...filters, [name]: event.target.value });
  };

  return {
    change,
    value: filters[name]
  };
};

export default useText;
