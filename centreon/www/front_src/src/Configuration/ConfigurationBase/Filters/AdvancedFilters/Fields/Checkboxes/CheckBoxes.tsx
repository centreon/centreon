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

import useCheckboxes from './useCheckboxes';

interface Props<TFilters> {
  name: string;
  label: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
  options: Array<{ id: string; name: string }>;
}

const Checkboxes = <TFilters,>({
  name,
  label,
  filters,
  setFilters,
  options
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { change, isChecked } = useCheckboxes<TFilters>({
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

export default Checkboxes;
