import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';
import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { useFilterStyles } from '../../../Filters.styles';

import useCheckBoxes from './useCheckBoxes';

interface Props<TFilters> {
  name: string;
  label: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
  options: Array<{ id: string; name: string }>;
}

const CheckBoxes = <TFilters,>({
  name,
  label,
  filters,
  setFilters,
  options
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { change, isChecked } = useCheckBoxes<TFilters>({
    filters,
    setFilters,
    name
  });

  return (
    <div className={classes.statusFilter}>
      <Typography className={classes.statusFilterName}>{t(label)}</Typography>
      <FormGroup row>
        {options.map(({ id, name }) => (
          <FormControlLabel
            control={
              <Checkbox
                data-testid={name}
                checked={isChecked(id)}
                name={id}
                onChange={change}
              />
            }
            key={id}
            label={t(name)}
          />
        ))}
      </FormGroup>
    </div>
  );
};

export default CheckBoxes;
