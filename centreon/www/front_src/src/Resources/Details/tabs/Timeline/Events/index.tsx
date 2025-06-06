import { RefObject } from 'react';

import { Dayjs } from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals, isEmpty, last, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  Timeline,
  TimelineDot,
  TimelineItem,
  TimelineSeparator
} from '@mui/lab';
import { Divider, Paper, Typography } from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelFrom, labelTo } from '../../../../translatedLabels';
import {
  TimelineEventByType,
  TimelineIconByType,
  eventsByDateDivisions,
  sortEventsByDate
} from '../Event';
import { TimelineEvent } from '../models';

const useStyles = makeStyles()((theme) => ({
  contentContainer: {
    paddingBottom: 0,
    paddingTop: 0
  },
  divider: {
    backgroundColor: theme.palette.action.disabled
  },
  dividerContainer: {
    display: 'flex',
    height: 12,
    paddingLeft: 18
  },
  divisionSubtitle: {
    marginLeft: theme.spacing(4)
  },
  event: {
    '&:before': {
      flex: 0,
      padding: 0
    },
    alignItems: 'center',
    minHeight: theme.spacing(7)
  },
  events: {
    display: 'grid',
    gridAutoFlow: 'row',
    width: '100%'
  },
  header: {
    paddingBottom: theme.spacing(1)
  },
  timeline: {
    margin: 0,
    padding: theme.spacing(0, 2, 0.5, 2)
  },
  timelineDot: {
    '> div, svg': {
      height: theme.spacing(2.75),
      width: theme.spacing(2.75)
    },
    alignItems: 'center',
    boxSizing: 'content-box',
    display: 'grid',
    height: theme.spacing(3),
    justifyItems: 'center',
    width: theme.spacing(3)
  }
}));

interface Props {
  infiniteScrollTriggerRef: RefObject<HTMLDivElement>;
  timeline: Array<TimelineEvent>;
}

const Events = ({ timeline, infiniteScrollTriggerRef }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { locale } = useAtomValue(userAtom);
  const { format } = useLocaleDateTimeFormat();

  const lastEvent = last(timeline.sort(sortEventsByDate));

  const formattedLocale = locale.substring(0, 2);

  return (
    <div aria-label="test">
      {eventsByDateDivisions.map(
        ({
          label,
          getEventsByDate,
          startDate,
          endDate
        }): JSX.Element | null => {
          const eventsByDate = getEventsByDate({
            events: timeline,
            locale: formattedLocale
          });

          if (isEmpty(eventsByDate)) {
            return null;
          }

          const formattedStartDate = startDate
            ? [
                t(labelFrom),
                format({
                  date: startDate(formattedLocale).toISOString(),
                  formatString: 'LL'
                })
              ]
            : [];

          const formattedDivisionDates = endDate
            ? [
                ...(formattedStartDate || []),
                t(labelTo).toLowerCase(),
                format({
                  date: endDate(formattedLocale).toISOString(),
                  formatString: 'LL'
                })
              ]
            : formattedStartDate;

          const areStartAndEndDateEqual =
            not(isEmpty(formattedDivisionDates)) &&
            equals(formattedDivisionDates[1], formattedDivisionDates[3]);

          const eventDate = areStartAndEndDateEqual
            ? format({
                date: (startDate?.(formattedLocale) as Dayjs)?.toISOString(),
                formatString: 'LL'
              })
            : formattedDivisionDates.join(' ');

          return (
            <div key={label}>
              <div className={classes.events}>
                <Timeline className={classes.timeline}>
                  <Typography
                    className={classes.header}
                    display="inline"
                    variant="h6"
                  >
                    {t(label)}
                    <span className={classes.divisionSubtitle}>
                      <Typography display="inline">{eventDate}</Typography>
                    </span>
                  </Typography>
                  {eventsByDate.map((event) => {
                    const { id, type } = event;

                    const Event = TimelineEventByType[type];

                    const icon = TimelineIconByType[type];

                    const isNotLastEvent = not(
                      equals(event, last(eventsByDate))
                    );

                    return (
                      <div key={`${id}-${type}`}>
                        <TimelineItem className={classes.event}>
                          <TimelineSeparator>
                            <TimelineDot
                              className={classes.timelineDot}
                              variant="outlined"
                            >
                              {icon(t)}
                            </TimelineDot>
                          </TimelineSeparator>
                          <div className={`pl-4 ${classes.contentContainer}`}>
                            <Paper>
                              <Event event={event} />
                            </Paper>
                            {equals(lastEvent, event) && (
                              <div ref={infiniteScrollTriggerRef} />
                            )}
                          </div>
                        </TimelineItem>
                        {isNotLastEvent && (
                          <div className={classes.dividerContainer}>
                            <Divider
                              flexItem
                              className={classes.divider}
                              orientation="vertical"
                            />
                          </div>
                        )}
                      </div>
                    );
                  })}
                </Timeline>
              </div>
            </div>
          );
        }
      )}
    </div>
  );
};

export default Events;
