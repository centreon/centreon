import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import dayjs from 'dayjs';

import QueryBuilderIcon from '@mui/icons-material/QueryBuilder';
import { Typography } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { PanelOptions } from './models';
import { useClockStyles } from './Clock.styles';
import CustomFluidTypography from './CustomFluidTypography';

const Clock = ({
  timezone,
  locale,
  showTimezone,
  showDate,
  backgroundColor
}: PanelOptions): JSX.Element => {
  const { classes } = useClockStyles();
  const user = useAtomValue(userAtom);

  const timezoneToUse = useMemo(
    () => (timezone?.id ?? user.timezone) as string,
    [user.timezone, timezone]
  );
  const localeToUse = useMemo(
    () => (locale?.id ?? user.locale) as string,
    [user.locale, locale]
  );

  const currentDate = dayjs().locale(localeToUse).tz(timezoneToUse);

  const isMeridiem = useMemo(
    () => dayjs().locale(localeToUse).format('LT').length > 5,
    [localeToUse]
  );

  const hours = useMemo(
    () => currentDate.format(isMeridiem ? 'hh' : 'HH'),
    [isMeridiem, currentDate]
  );
  const minutes = useMemo(() => currentDate.format('mm'), [currentDate]);
  const meridiem = useMemo(() => currentDate.format('A'), [currentDate]);

  return (
    <CustomFluidTypography>
      {(fontSize) => (
        <>
          <div className={classes.container}>
            <div className={classes.clockInformation}>
              <QueryBuilderIcon className={classes.icon} />
              {showTimezone ? (
                <Typography className={classes.timezone}>
                  {timezoneToUse}
                </Typography>
              ) : (
                <div />
              )}
              {showDate ? (
                <Typography className={classes.date} fontWeight="bold">
                  {currentDate.format('L')}
                </Typography>
              ) : (
                <div />
              )}
            </div>
            <div className={classes.clockLabel}>
              <Typography
                component="span"
                fontSize={`${fontSize}px`}
              >{`${hours}:${minutes}`}</Typography>
              {isMeridiem && (
                <Typography
                  component="span"
                  fontSize={`${fontSize / 3}px`}
                  lineHeight={3}
                >
                  {meridiem}
                </Typography>
              )}
            </div>
          </div>
          <div
            className={classes.background}
            style={{
              backgroundColor: backgroundColor ?? '#255891'
            }}
          />
        </>
      )}
    </CustomFluidTypography>
  );
};

export default Clock;
