import { CircularProgress, Typography } from '@mui/material';

import { LoadingSkeleton } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import DayHeader, { useDayKey } from '../Listing/DayHeader';
import type { ActivityLog } from '../models';
import { labelBeginningOfHistory } from '../translatedLabels';
import TimelineEvent from './TimelineEvent';

interface Props {
  elementRef: (node: Element | null) => void;
  elements: Array<ActivityLog>;
  hasNextPage: boolean;
  isLoading: boolean;
}

// The history of one object, most recent first, grouped by day
const Timeline = ({
  elementRef,
  elements,
  hasNextPage,
  isLoading
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const getDayKey = useDayKey();

  if (isLoading && elements.length === 0) {
    return (
      <div className="flex flex-col gap-3 p-4">
        <LoadingSkeleton height={20} width="40%" />
        <LoadingSkeleton height={20} width="70%" />
        <LoadingSkeleton height={20} width="60%" />
      </div>
    );
  }

  return (
    <div className="flex flex-col px-4 pb-4">
      <ol className="m-0 list-none p-0">
        {elements.map((event, index) => {
          const isFirstOfDay =
            index === 0 ||
            getDayKey(event.date) !== getDayKey(elements[index - 1].date);

          return (
            <div className="contents" key={event.id}>
              {isFirstOfDay && <DayHeader date={event.date} sticky={false} />}
              <TimelineEvent
                event={event}
                isLast={!hasNextPage && index === elements.length - 1}
              />
            </div>
          );
        })}
      </ol>
      <div
        className="flex h-10 items-center justify-center"
        ref={hasNextPage ? elementRef : undefined}
      >
        {hasNextPage ? (
          <CircularProgress size={20} />
        ) : (
          <Typography className="text-text-secondary" variant="caption">
            {t(labelBeginningOfHistory)}
          </Typography>
        )}
      </div>
    </div>
  );
};

export default Timeline;
