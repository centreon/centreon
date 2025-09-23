import { FormControlLabel, Switch } from '@mui/material';
import { useAtom } from 'jotai';
import { ChangeEvent } from 'react';
import { filtersAtom } from '../atoms';

const Filters = () => {
  const [filters, setFilters] = useAtom(filtersAtom);

  const change =
    (property: string) => (event: ChangeEvent<HTMLInputElement>) => {
      setFilters((current) => ({
        ...current,
        [property]: event.target.checked
      }));
    };

  return (
    <>
      <FormControlLabel
        control={
          <Switch
            checked={filters.hasDescription}
            onChange={change('hasDescription')}
          />
        }
        label="Has description"
      />
      <FormControlLabel
        control={
          <Switch checked={filters.isEven} onChange={change('isEven')} />
        }
        label="Is even"
      />
    </>
  );
};

export default Filters;
