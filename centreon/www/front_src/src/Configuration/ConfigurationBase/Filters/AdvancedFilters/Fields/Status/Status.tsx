import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

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

interface Props<TFilters> {
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const Status = <TFilters,>({
  filters,
  setFilters
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const { valueEnable, valueDisable, change } = useStatus<TFilters>({
    filters,
    setFilters
  });

  return (
    <div className="flex flex-col justify-between items-start pl-2">
      <Typography className="font-medium">{t(labelStatus)}</Typography>
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
