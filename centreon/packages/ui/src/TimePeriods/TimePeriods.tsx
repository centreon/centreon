import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import duration from 'dayjs/plugin/duration';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { lt } from 'ramda';

import { Paper, Theme, useMediaQuery } from '@mui/material';

import CustomTimePeriod from './CustomTimePeriod';
import SelectedTimePeriod from './SelectedTimePeriod';
import { useStyles } from './TimePeriods.styles';
import useTimePeriod from './useTimePeriod';
import { WrapperTimePeriodProps } from './models';

dayjs.extend(isSameOrAfter);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

const TimePeriods = ({
  extraTimePeriods,
  disabled = false,
  getParameters,
  getIsError,
  adjustTimePeriodData,
  renderExternalComponent,
  width
}: WrapperTimePeriodProps & { width: number }): JSX.Element => {
  const { classes, cx } = useStyles({ disabled });

  useTimePeriod({
    adjustTimePeriodData,
    getIsError,
    getParameters
  });

  const isCondensed =
    useMediaQuery((theme: Theme) => theme.breakpoints.down('sm')) ||
    lt(width, 600);

  return (
    <Paper className={cx(classes.header, { [classes.condensed]: isCondensed })}>
      <SelectedTimePeriod
        disabled={disabled}
        extraTimePeriods={extraTimePeriods}
        width={width}
      />
      <CustomTimePeriod disabled={disabled} isCondensed={isCondensed} />
      <div>{renderExternalComponent}</div>
    </Paper>
  );
};

export default TimePeriods;
