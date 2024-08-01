import { memo } from 'react';

import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import QueryBuilderIcon from '@mui/icons-material/QueryBuilder';
import HourglassTopIcon from '@mui/icons-material/HourglassTop';
import PublicIcon from '@mui/icons-material/Public';
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
    <Tooltip placement="top" title={timezone}>
      <PublicIcon className={classes.timezone} />
    </Tooltip>
  ) : (
    <Tooltip placement="top" title={timezone}>
      <div>
        <EllipsisTypography className={classes.timezone}>
          {timezone}
        </EllipsisTypography>
      </div>
    </Tooltip>
  );

  const dateDisplay = isClock ? (
    <Typography className={classes.date} fontWeight="bold">
      {date.format('L')}
    </Typography>
  ) : (
    <Tooltip
      placement="top"
      title={`${t(labelEndsAt)}: ${date.format('L LT')}`}
    >
      <div>
        <EllipsisTypography
          className={classes.date}
          fontWeight="bold"
        >{`${t(labelEndsAt)}: ${date.format('L LT')}`}</EllipsisTypography>
      </div>
    </Tooltip>
  );

  return (
    <div
      className={classes.clockInformation}
      data-isSmall={isSmall}
      data-timer={!isClock}
    >
      <Icon className={classes.icon} />
      {showTimezone ? timezoneDisplay : <div />}
      {showDate ? dateDisplay : <div />}
    </div>
  );
};

export default memo(ClockInformation, equals);
