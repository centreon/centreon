import { JSX } from 'react';
import { useTranslation } from 'react-i18next';
import { useFilterStyles } from '../../../Filters.styles';

import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';

import useStatus from './useStatus';

import {
  labelDisabled,
  labelEnabled,
  labelStatus
} from '../../../../translatedLabels';

const Status = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { valueEnable, valueDisable, change } = useStatus();

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
