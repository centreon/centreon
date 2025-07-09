import { useTranslation } from 'react-i18next';

import {
  labelDisabled,
  labelEnabled,
  labelStatus
} from '../../../translatedLabels';

import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';

import { useFilterStyles } from '../../Filters.styles';

const Status = ({ change, filters }): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

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
              checked={filters.enabled}
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
              checked={filters.disabled}
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
