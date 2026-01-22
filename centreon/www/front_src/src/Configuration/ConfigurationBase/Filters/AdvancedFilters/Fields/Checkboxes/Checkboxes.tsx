import { Checkbox, FormControlLabel, Typography } from '@mui/material';

import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

import useCheckboxes from './useCheckboxes';

interface Props<TFilters> {
  name: string;
  label: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
  options: Array<{ id: string; name: string; disabled?: boolean }>;
}

const Checkboxes = <TFilters,>({
  name,
  label,
  filters,
  setFilters,
  options
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const { change, isChecked } = useCheckboxes<TFilters>({
    filters,
    name,
    setFilters
  });

  return (
    <div className="flex flex-col justify-between items-start pl-2">
      <Typography className="font-medium">{t(label)}</Typography>
      <div className="grid grid-cols-2">
        {options.map(({ id, name, disabled }) => (
          <FormControlLabel
            control={
              <Checkbox
                checked={isChecked(id)}
                data-testid={name}
                name={id}
                onChange={change}
              />
            }
            disabled={disabled}
            key={id}
            label={t(name)}
          />
        ))}
      </div>
    </div>
  );
};

export default Checkboxes;
