import { useMemo } from 'react';

import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { useClockStyles } from './Clock.styles';
import ClockInformation from './ClockInformation';
import { PanelOptions } from './models';
import { useGetLocaleAndTimezone } from './useGetLocaleAndTimezone';
import { labelPleaseSelectAValidCountdown } from './translatedLabels';
import CustomFluidTypography from './CustomFluidTypography';
import BackgroundColor from './BackgroundColor';

const Timer = ({
  countdown,
  showDate,
  showTimezone,
  timezone,
  locale,
  hasDescription,
  backgroundColor
}: PanelOptions & { hasDescription: boolean }): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useClockStyles();

  const { locale: localeToUse, timezone: timezoneToUse } =
    useGetLocaleAndTimezone({ locale, timezone });

  const dateFromCountDown = useMemo(
    () => countdown && dayjs(countdown).locale(localeToUse).tz(timezoneToUse),
    [countdown, localeToUse, timezoneToUse]
  );

  if (!dateFromCountDown) {
    return <Typography>{t(labelPleaseSelectAValidCountdown)}</Typography>;
  }

  return (
    <CustomFluidTypography>
      {({ width }) => (
        <>
          <div className={classes.container}>
            <ClockInformation
              date={dateFromCountDown}
              isClock={false}
              showDate={showDate}
              showTimezone={showTimezone}
              timezone={timezoneToUse}
              width={width}
            />
          </div>
          <BackgroundColor
            backgroundColor={backgroundColor}
            hasDescription={hasDescription}
          />
        </>
      )}
    </CustomFluidTypography>
  );
};

export default Timer;
