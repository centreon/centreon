import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';

import {
  labelDisabled,
  labelEnabled,
  labelStatus
} from '../../../translatedLabels';
import { filtersAtom } from '../../atoms';

import { useStyles } from './Filters.styles';

const Status = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const change =
    (key) =>
    (event): void => {
      setFilters({ ...filters, [key]: event.target.checked });
    };

  return (
    <div className={classes.statusFilter}>
      <Typography className={classes.statusFilterName}>
        {t(labelStatus)}
      </Typography>
      <FormGroup row>
        <FormControlLabel
          control={
            <Checkbox
              checked={filters.enabled}
              data-testid={labelEnabled}
              name={t(labelEnabled)}
              onChange={change('enabled')}
            />
          }
          label={t(labelEnabled)}
        />
        <FormControlLabel
          control={
            <Checkbox
              checked={filters.disabled}
              data-testid={labelDisabled}
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
