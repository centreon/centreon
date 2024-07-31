import { useMemo } from 'react';

import { useAtomValue } from 'jotai';

import QueryBuilderIcon from '@mui/icons-material/QueryBuilder';
import { Typography } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { PanelOptions } from './models';
import { useClockStyles } from './Clock.styles';

const Clock = ({
  timezone,
  locale,
  showTimezone,
  showDate
}: PanelOptions): JSX.Element => {
  const { classes } = useClockStyles();
  const user = useAtomValue(userAtom);

  const timezoneToUse = useMemo(
    () => timezone ?? user.timezone,
    [user.timezone, timezone]
  );
  const localeToUse = useMemo(
    () => locale ?? user.locale,
    [user.locale, timezone]
  );

  return (
    <div className={classes.container}>
      <div className={classes.clockInformation}>
        <QueryBuilderIcon className={classes.icon} />
        {showTimezone && (
          <Typography className={classes.timezone}>{timezoneToUse}</Typography>
        )}
        {showDate && (
          <Typography className={classes.date}>{localeToUse}</Typography>
        )}
      </div>
      <div />
    </div>
  );
};

export default Clock;
