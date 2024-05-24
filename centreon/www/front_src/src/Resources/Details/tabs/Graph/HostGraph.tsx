import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';

import type { ListingModel } from '@centreon/ui';
import { TimePeriods, useRequest } from '@centreon/ui';

import { TabProps } from '..';
import GraphOptions from '../../../Graph/Performance/ExportableGraphWithTimeline/GraphOptions';
import { updatedGraphIntervalAtom } from '../../../Graph/Performance/ExportableGraphWithTimeline/atoms';
import { listResources } from '../../../Listing/api';
import { Resource } from '../../../models';
import InfiniteScroll from '../../InfiniteScroll';
import ServiceGraphs from '../Services/Graphs';
import LoadingSkeleton from '../Timeline/LoadingSkeleton';

const HostGraph = ({ details }: TabProps): JSX.Element => {
  const [graphTimeParameters, setGraphTimeParameters] = useState();

  const { sendRequest, sending } = useRequest({
    request: listResources
  });

  const updatedGraphInterval = useAtomValue(updatedGraphIntervalAtom);

  const limit = 6;

  const sendListingRequest = ({
    atPage
  }: {
    atPage?: number;
  }): Promise<ListingModel<Resource>> => {
    return sendRequest({
      limit,
      onlyWithPerformanceData: true,
      page: atPage,
      resourceTypes: ['service'],
      search: {
        conditions: [
          {
            field: 'h.name',
            values: {
              $eq: details?.name
            }
          }
        ]
      }
    });
  };

  const getTimePeriodsParameters = (data): void => {
    setGraphTimeParameters(data);
  };

  const newGraphInterval = updatedGraphInterval
    ? { end: updatedGraphInterval.end, start: updatedGraphInterval.start }
    : undefined;

  return (
    <InfiniteScroll<Resource>
      details={details}
      filter={
        <TimePeriods
          adjustTimePeriodData={newGraphInterval}
          getParameters={getTimePeriodsParameters}
          renderExternalComponent={<GraphOptions />}
        />
      }
      limit={limit}
      loading={sending}
      loadingSkeleton={<LoadingSkeleton />}
      preventReloadWhen={isNil(details)}
      sendListingRequest={sendListingRequest}
    >
      {({ infiniteScrollTriggerRef, entities }): JSX.Element => {
        return (
          <ServiceGraphs
            graphTimeParameters={graphTimeParameters}
            infiniteScrollTriggerRef={infiniteScrollTriggerRef}
            services={entities}
          />
        );
      }}
    </InfiniteScroll>
  );
};

export default HostGraph;
