import { Typography } from '@mui/material';

import { useLocale } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { labelToday, labelYesterday } from '../translatedLabels';

dayjs.extend(utc);
dayjs.extend(timezone);

const dayKeyFormat = 'YYYY-MM-DD';

// Days are cut in the user's timezone, not the browser's
export const useDayKey = (): ((date: string) => string) => {
  const { timezone: userTimezone } = useAtomValue(userAtom);

  return (date) => dayjs(date).tz(userTimezone).format(dayKeyFormat);
};

interface Props {
  date: string;
  // Sticks under the header row of the listing
  sticky?: boolean;
}

const DayHeader = ({ date, sticky = true }: Props): JSX.Element => {
  const { t } = useTranslation();
  const locale = useLocale().substring(0, 2);
  const { timezone: userTimezone } = useAtomValue(userAtom);

  const day = dayjs(date).tz(userTimezone).locale(locale);
  const today = dayjs().tz(userTimezone);

  const getLabel = (): string => {
    if (day.isSame(today, 'day')) {
      return t(labelToday);
    }

    if (day.isSame(today.subtract(1, 'day'), 'day')) {
      return t(labelYesterday);
    }

    return day.format(day.isSame(today, 'year') ? 'dddd D MMMM' : 'dddd LL');
  };

  return (
    <div
      className={
        sticky
          ? 'sticky top-[30px] z-[1] border-0 border-b border-solid border-b-divider bg-background-paper px-3 pt-4 pb-1'
          : 'pt-3 pb-2'
      }
    >
      <Typography
        className="font-bold text-text-secondary first-letter:uppercase"
        variant="body2"
      >
        {getLabel()}
      </Typography>
    </div>
  );
};

export default DayHeader;
