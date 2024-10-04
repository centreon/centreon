import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { isEmpty, isNil, path, prop } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Paper, Stack } from '@mui/material';

import type { ListingModel, SearchParameter } from '@centreon/ui';
import { MultiAutocompleteField, useRequest } from '@centreon/ui';

import { TabProps } from '..';
import TimePeriodButtonGroup from '../../../Graph/Performance/TimePeriods';
import {
  customTimePeriodAtom,
  getDatesDerivedAtom,
  selectedTimePeriodAtom
} from '../../../Graph/Performance/TimePeriods/timePeriodAtoms';
import { labelEvent } from '../../../translatedLabels';
import InfiniteScroll from '../../InfiniteScroll';

import AddCommentArea from './Addcomment/AddCommentArea';
import AddCommentButton from './Addcomment';
import { types } from './Event';
import Events from './Events';
import ExportToCsv from './ExportToCsv';
import LoadingSkeleton from './LoadingSkeleton';
import { listTimelineEvents } from './api';
import { listTimelineEventsDecoder } from './api/decoders';
import { TimelineEvent, Type } from './models';

type TimelineListing = ListingModel<TimelineEvent>;

const useStyles = makeStyles()((theme) => ({
  containerActions: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    marginBottom: 1,
    marginTop: theme.spacing(1)
  },
  filterHeader: {
    alignItems: 'center',
    display: 'grid',
    padding: theme.spacing(1)
  }
}));

const TimelineTab = ({ details }: TabProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const translatedTypes = types.map((type) => ({
    ...type,
    name: t(type.name)
  })) as Array<Type>;

  const [selectedTypes, setSelectedTypes] =
    useState<Array<Type>>(translatedTypes);

  const [displayCommentArea, setDisplayCommentArea] = useState(false);

  const { sendRequest, sending } = useRequest<TimelineListing>({
    decoder: listTimelineEventsDecoder,
    request: listTimelineEvents
  });

  const getIntervalDates = useAtomValue(getDatesDerivedAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const customTimePeriod = useAtomValue(customTimePeriodAtom);

  const [start, end] = getIntervalDates(selectedTimePeriod);

  const limit = 30;

  const getSearch = (): SearchParameter | undefined => {
    if (isEmpty(selectedTypes)) {
      return undefined;
    }

    return {
      conditions: [
        {
          field: 'date',
          values: {
            $gt: start,
            $lt: end
          }
        }
      ],
      lists: [
        {
          field: 'type',
          values: selectedTypes.map(prop('id'))
        }
      ]
    };
  };

  const timelineEndpoint = path(['links', 'endpoints', 'timeline'], details);
  const timelineDownloadEndpoint = path(
    ['links', 'endpoints', 'timeline_download'],
    details
  );

  const listTimeline = ({
    atPage
  }: {
    atPage?: number;
  }): Promise<TimelineListing> => {
    return sendRequest({
      endpoint: timelineEndpoint,
      parameters: {
        limit,
        page: atPage,
        search: getSearch()
      }
    });
  };

  const changeSelectedTypes = (_, typeIds): void => {
    setSelectedTypes(typeIds);
  };

  const prepareToAddComment = (): void => {
    setDisplayCommentArea(true);
  };

  const closeCommentArea = (): void => {
    setDisplayCommentArea(false);
  };

  const displayCsvExport = !isNil(timelineDownloadEndpoint);
  const enableCommentArea = !isNil(details) && displayCommentArea;
  const onDelete = (_, option): void => {
    const updatedTypeIds = selectedTypes.filter(
      ({ id }) => !equals(id, option.id)
    );

    setSelectedTypes(updatedTypeIds);
  };

  return (
    <InfiniteScroll
      details={details}
      filter={
        <Stack data-testid="headerWrapper" spacing={0.5}>
          <Paper className={classes.filterHeader}>
            <TimePeriodButtonGroup disableGraphOptions disablePaper />
            <MultiAutocompleteField
              chipProps={{ onDelete }}
              label={t(labelEvent)}
              limitTags={3}
              options={translatedTypes}
              value={selectedTypes}
              onChange={changeSelectedTypes}
            />
          </Paper>
          <div className={classes.containerActions}>
            {details && (
              <AddCommentButton
                resources={[details]}
                onClick={prepareToAddComment}
              />
            )}
            {displayCsvExport && (
              <ExportToCsv
                getSearch={getSearch}
                timelineDownloadEndpoint={timelineDownloadEndpoint as string}
              />
            )}
          </div>
          {enableCommentArea && (
            <AddCommentArea
              closeCommentArea={closeCommentArea}
              resources={[details]}
            />
          )}
        </Stack>
      }
      limit={limit}
      loading={sending}
      loadingSkeleton={<LoadingSkeleton />}
      reloadDependencies={[
        selectedTypes,
        selectedTimePeriod?.id || customTimePeriod,
        timelineEndpoint
      ]}
      sendListingRequest={isNil(timelineEndpoint) ? undefined : listTimeline}
    >
      {({ infiniteScrollTriggerRef, entities }): JSX.Element => {
        return (
          <Events
            infiniteScrollTriggerRef={infiniteScrollTriggerRef}
            timeline={entities}
          />
        );
      }}
    </InfiniteScroll>
  );
};

export default TimelineTab;
