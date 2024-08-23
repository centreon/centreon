import { memo } from 'react';

import { Dayjs } from 'dayjs';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import HourglassTopIcon from '@mui/icons-material/HourglassTop';
import PublicIcon from '@mui/icons-material/Public';
import QueryBuilderIcon from '@mui/icons-material/QueryBuilder';
import { Tooltip, Typography } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { useClockStyles } from './Clock.styles';
import { labelEndsAt } from './translatedLabels';

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
  const { t } = useTranslation();
  const { classes } = useClockStyles();

  const Icon = isClock ? QueryBuilderIcon : HourglassTopIcon;

  const isSmall = width < (isClock ? 200 : 300);

  const timezoneDisplay = isSmall ? (
    <PublicIcon className={classes.timezone} />
  ) : (
    <EllipsisTypography className={classes.timezone}>
      {timezone}
    </EllipsisTypography>
  );

  const dateDisplay = isClock ? (
    <Typography className={classes.date} fontWeight="bold">
      {date.format('L')}
    </Typography>
  ) : (
    <EllipsisTypography
      className={classes.date}
      fontWeight="bold"
    >{`${t(labelEndsAt)}: ${date.valueOf() > 0 ? date.format('L LT') : ''}`}</EllipsisTypography>
  );

  return (
    <div
      className={classes.clockInformation}
      data-isSmall={isSmall}
      data-timer={!isClock}
    >
      <Icon className={classes.icon} />
      {showTimezone ? (
        <Tooltip placement="top" title={timezone}>
          <div>{timezoneDisplay}</div>
        </Tooltip>
      ) : (
        <div />
      )}
      {showDate ? (
        <Tooltip
          placement="top"
          title={
            isClock
              ? date.format('L')
              : `${t(labelEndsAt)}: ${date.format('L LT')}`
          }
        >
          <div>{dateDisplay}</div>
        </Tooltip>
      ) : (
        <div />
      )}
    </div>
  );
};

export default memo(ClockInformation, equals);
