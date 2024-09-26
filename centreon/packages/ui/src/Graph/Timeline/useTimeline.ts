import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import relativeTime from 'dayjs/plugin/relativeTime';
import timezonePlugin from 'dayjs/plugin/timezone';

import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';

import { useTranslation } from 'react-i18next';
import { usePluralizedTranslation } from '@centreon/ui';
import {
  labelDay,
  labelHour,
  labelMinute,
  labelMinutes,
  labelMonth,
  labelYear
} from './translatedLabel';

dayjs.extend(duration);
dayjs.extend(localizedFormat);
dayjs.extend(timezonePlugin);
dayjs.extend(relativeTime);

export const useTimeline = ({ locale = 'en' }) => {
  const { t } = useTranslation();
  const { pluralizedT } = usePluralizedTranslation();

  dayjs.locale(locale);

  const getTimeDifference = (startDate, endDate) => {
    const start = dayjs(startDate);
    const end = dayjs(endDate);

    const diffInMilliseconds = Math.abs(end - start);
    const diffDuration = dayjs.duration(diffInMilliseconds);

    const timeUnits = [
      {
        value: diffDuration.years(),
        unit: pluralizedT({ label: labelYear, count: diffDuration.years() })
      },
      {
        value: diffDuration.hours(),
        unit: pluralizedT({ label: labelMonth, count: diffDuration.hours() })
      },
      {
        value: diffDuration.days(),
        unit: pluralizedT({ label: labelDay, count: diffDuration.days() })
      },
      {
        value: diffDuration.hours(),
        unit: pluralizedT({ label: labelHour, count: diffDuration.hours() })
      },
      {
        value: diffDuration.minutes(),
        unit: pluralizedT({ label: labelMinute, count: diffDuration.minutes() })
      }
    ];

    const readableUnits = timeUnits
      .filter((unit) => unit.value > 0)
      .map((unit) => `${unit.value} ${unit.unit}`);

    return readableUnits.slice(0, 2).join(', ') || `1 ${t(labelMinutes)}`;
  };

  return {
    getTimeDifference
  };
};
