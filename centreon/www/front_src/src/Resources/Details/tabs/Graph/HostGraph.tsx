import { useState } from 'react';

import { useAtom } from 'jotai';
import { isNil } from 'ramda';

import type { ListingModel } from '@centreon/ui';
import { TimePeriods, useRequest } from '@centreon/ui';

import type { TabProps } from '..';
import GraphOptions from '../../../Graph/Performance/ExportableGraphWithTimeline/GraphOptions';
import { listResources } from '../../../Listing/api';
import type { Resource } from '../../../models';
import InfiniteScroll from '../../InfiniteScroll';
import ServiceGraphs from '../Services/Graphs';
import LoadingSkeleton from '../Timeline/LoadingSkeleton';
import { updatedGraphIntervalAtom } from './atoms';
import type { GraphTimeParameters } from './models';

const HostGraph = ({ details }: TabProps): JSX.Element => {
  const [graphTimeParameters, setGraphTimeParameters] =
    useState<GraphTimeParameters>();

  const [updatedGraphInterval, setUpdatedGraphInterval] = useAtom(
    updatedGraphIntervalAtom
  );

  const { sendRequest, sending } = useRequest({
    request: listResources
  });

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

  const getTimePeriodsParameters = (data: GraphTimeParameters): void => {
    setGraphTimeParameters(data);
  };

  return (
    <InfiniteScroll<Resource>
      details={details}
      filter={
        <TimePeriods
          adjustTimePeriodData={updatedGraphInterval}
          getParameters={getTimePeriodsParameters}
          renderExternalComponent={<GraphOptions />}
        />
      }
      limit={limit}
      loading={sending}
      loadingSkeleton={<LoadingSkeleton />}
      preventReloadWhen={isNil(details)}
      sendListingRequest={sendListingRequest}
      graphTimeParameters={graphTimeParameters}
    >
      {({
        infiniteScrollTriggerRef,
        entities,
        graphTimeParameters
      }): JSX.Element => {
        return (
          <ServiceGraphs
            graphTimeParameters={graphTimeParameters}
            infiniteScrollTriggerRef={infiniteScrollTriggerRef}
            services={entities}
            updateGraphInterval={setUpdatedGraphInterval}
          />
        );
      }}
    </InfiniteScroll>
  );
};

export default HostGraph;
