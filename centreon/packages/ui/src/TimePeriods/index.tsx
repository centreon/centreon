import { useEffect } from 'react';

import { Responsive } from '@visx/visx';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import dayjs from 'dayjs';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import duration from 'dayjs/plugin/duration';
import { useAtomValue } from 'jotai';

import { Paper } from '@mui/material';

import { useStyles } from './timePeriods.styles';
import CustomTimePeriod from './CustomTimePeriod';
import SelectedTimePeriod from './SelectedTimePeriod';
import { EndStartInterval, TimePeriod } from './models';
import {
  customTimePeriodAtom,
  getDatesDerivedAtom,
  selectedTimePeriodAtom,
  errorTimePeriodAtom
} from './timePeriodsAtoms';

dayjs.extend(isSameOrAfter);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

interface Props {
  disabled?: boolean;
  extraTimePeriods?: Array<TimePeriod>;
  getIsError?: (value: boolean) => void;
  getStartEndParameters?: ({ start, end }: EndStartInterval) => void;
}

const AwesomeTimePeriod = ({
  extraTimePeriods,
  disabled = false,
  getStartEndParameters,
  getIsError
}: Props): JSX.Element => {
  const { classes } = useStyles({ disabled });

  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const getCurrentEndStartInterval = useAtomValue(getDatesDerivedAtom);
  const errorTimePeriod = useAtomValue(errorTimePeriodAtom);

  useEffect(() => {
    const [start, end] = getCurrentEndStartInterval(selectedTimePeriod);
    getStartEndParameters?.({ end, start });
  }, [customTimePeriod, selectedTimePeriod]);

  useEffect(() => {
    getIsError?.(errorTimePeriod);
  }, [errorTimePeriod]);

  return (
    <div>
      <Responsive.ParentSize>
        {({ width }): JSX.Element => {
          return (
            <Paper className={classes.header} style={{ width }}>
              <SelectedTimePeriod
                disabled={disabled}
                extraTimePeriods={extraTimePeriods}
                width={width}
              />
              <CustomTimePeriod disabled={disabled} width={width} />
            </Paper>
          );
        }}
      </Responsive.ParentSize>
    </div>
  );
};
export default AwesomeTimePeriod;
