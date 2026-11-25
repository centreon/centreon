import { Tooltip, Typography } from '@mui/material';

import { type ComponentColumnProps, useLocale } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import 'dayjs/locale/de';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';

import type { ActivityLog } from '../../models';

dayjs.extend(utc);
dayjs.extend(timezone);

// The day and how long ago it was are given by the group header, so the cell
// only shows the time; the full date and the timezone stay one hover away
const DateColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const locale = useLocale().substring(0, 2);
  const { timezone: userTimezone } = useAtomValue(userAtom);

  const date = dayjs((row as unknown as ActivityLog).date)
    .tz(userTimezone)
    .locale(locale);

  return (
    <Tooltip
      placement="top-start"
      title={`${date.format('LLLL')} (${userTimezone})`}
    >
      <Typography className="tabular-nums" component="span" variant="body2">
        {date.format('LT')}
      </Typography>
    </Tooltip>
  );
};

export default DateColumn;
