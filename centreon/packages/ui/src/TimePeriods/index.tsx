import { Responsive } from '@visx/visx';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import { makeStyles } from 'tss-react/mui';
import dayjs from 'dayjs';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import duration from 'dayjs/plugin/duration';

import { Paper } from '@mui/material';

import CustomTimePeriod from './CustomTimePeriod';
import SelectedTimePeriod from './SelectedTimePeriod';
import { TimePeriod } from './models';

dayjs.extend(isSameOrAfter);
interface StylesProps {
  disabled: boolean;
}

dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

const useStyles = makeStyles<StylesProps>()((theme, { disabled }) => ({
  header: {
    alignItems: 'center',
    backgroundColor: disabled ? 'transparent' : 'undefined',
    border: disabled ? 'unset' : 'undefined',
    boxShadow: disabled ? 'unset' : 'undefined',
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: `repeat(4, auto)`,
    gridTemplateRows: '1fr',
    justifyContent: 'center',
    padding: theme.spacing(1, 0.5)
  }
}));
interface Props {
  disabled?: boolean;
  extraTimePeriods?: Array<TimePeriod>;
}

const AwesomeTimePeriod = ({
  extraTimePeriods,
  disabled = false
}: Props): JSX.Element => {
  const { classes } = useStyles({ disabled });

  return (
    <Responsive.ParentSize>
      {({ width }): JSX.Element => {
        return (
          <Paper className={classes.header}>
            <SelectedTimePeriod
              extraTimePeriods={extraTimePeriods}
              width={width}
            />
            <CustomTimePeriod width={width} />
          </Paper>
        );
      }}
    </Responsive.ParentSize>
  );
};
export default AwesomeTimePeriod;
