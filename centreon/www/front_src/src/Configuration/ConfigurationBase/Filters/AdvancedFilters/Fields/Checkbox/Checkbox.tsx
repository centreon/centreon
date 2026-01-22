import { Checkbox as CheckboxComponent, FormControlLabel } from '@mui/material';

import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

interface Props<TFilters> {
  name: string;
  label: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
  options: Array<{ id: string; name: string }>;
}

const Checkbox = <TFilters,>({
  name,
  label,
  filters,
  setFilters
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const change = (_, checked): void => {
    setFilters({ ...filters, [name]: checked });
  };

  return (
    <div className="pl-2">
      <FormControlLabel
        control={
          <CheckboxComponent
            checked={filters[name]}
            data-testid={label}
            name={'id'}
            onChange={change}
          />
        }
        label={t(label)}
      />
    </div>
  );
};

export default Checkbox;
