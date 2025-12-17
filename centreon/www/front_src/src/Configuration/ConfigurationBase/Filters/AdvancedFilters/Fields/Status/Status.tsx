import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';

import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelDisabled,
  labelEnabled,
  labelStatus
} from '../../../../translatedLabels';
import { useFilterStyles } from '../../../Filters.styles';
import useStatus from './useStatus';

interface Props<TFilters> {
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const Status = <TFilters,>({
  filters,
  setFilters
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { valueEnable, valueDisable, change } = useStatus<TFilters>({
    filters,
    setFilters
  });

  return (
    <div className={classes.statusFilter}>
      <Typography className={classes.statusFilterName}>
        {t(labelStatus)}
      </Typography>
      <FormGroup row>
        <FormControlLabel
          control={
            <Checkbox
              data-testid={labelEnabled}
              checked={valueEnable}
              name={t(labelEnabled)}
              onChange={change('enabled')}
            />
          }
          label={t(labelEnabled)}
        />
        <FormControlLabel
          control={
            <Checkbox
              data-testid={labelDisabled}
              checked={valueDisable}
              name={t(labelDisabled)}
              onChange={change('disabled')}
            />
          }
          label={t(labelDisabled)}
        />
      </FormGroup>
    </div>
  );
};

export default Status;
