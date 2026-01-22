import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { filtersAtom } from '../atoms';
import { labelDisabled, labelEnabled, labelStatus } from '../translatedLabels';

const Status = (): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const change =
    (key) =>
    (event): void => {
      setFilters({ ...filters, [key]: event.target.checked });
    };

  return (
    <div className="flex flex-col justify-between items-start pl-2">
      <Typography className="font-medium">{t(labelStatus)}</Typography>
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
