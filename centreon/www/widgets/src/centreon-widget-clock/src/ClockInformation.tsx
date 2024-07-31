import { Dayjs } from 'dayjs';

import QueryBuilderIcon from '@mui/icons-material/QueryBuilder';
import HourglassTopIcon from '@mui/icons-material/HourglassTop';
import PublicIcon from '@mui/icons-material/Public';
import { Tooltip, Typography } from '@mui/material';

import { useClockStyles } from './Clock.styles';

interface Props {
  date: Dayjs;
  isClock: boolean;
  showDate: boolean;
  showTimezone: boolean;
  timezone: string;
  width: number;
}

const ClockInformation = ({
  showTimezone,
  showDate,
  date,
  timezone,
  isClock,
  width
}: Props): JSX.Element => {
  const { classes } = useClockStyles();

  const Icon = isClock ? QueryBuilderIcon : HourglassTopIcon;

  const isSmall = width < 200;

  const timezoneDisplay = isSmall ? (
    <Tooltip placement="top" title={timezone}>
      <PublicIcon className={classes.timezone} />
    </Tooltip>
  ) : (
    <Typography className={classes.timezone}>{timezone}</Typography>
  );

  return (
    <div className={classes.clockInformation} data-isSmall={isSmall}>
      <Icon className={classes.icon} />
      {showTimezone ? timezoneDisplay : <div />}
      {showDate ? (
        <Typography className={classes.date} fontWeight="bold">
          {date.format('L')}
        </Typography>
      ) : (
        <div />
      )}
    </div>
  );
};

export default ClockInformation;
