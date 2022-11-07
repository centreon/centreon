<<<<<<< HEAD
import { path } from 'ramda';
import { useAtomValue } from 'jotai/utils';

import { useRequest } from '@centreon/ui';

import InfiniteScroll from '../../InfiniteScroll';
import LoadingSkeleton from '../Services/LoadingSkeleton';
import { detailsAtom } from '../../detailsAtoms';
=======
import * as React from 'react';

import { path } from 'ramda';

import { useRequest } from '@centreon/ui';

import { TabProps } from '..';
import InfiniteScroll from '../../InfiniteScroll';
import LoadingSkeleton from '../Services/LoadingSkeleton';
import memoizeComponent from '../../../memoizedComponent';
import { useResourceContext, ResourceContext } from '../../../Context';
>>>>>>> centreon/dev-21.10.x

import { MetaServiceMetricListing } from './models';
import { listMetaServiceMetrics } from './api';
import { metaServiceMetricListingDecoder } from './api/decoders';
import Metrics from './Metrics';

const limit = 30;

<<<<<<< HEAD
const MetricsTab = (): JSX.Element => {
=======
type MetricsTabContentProps = TabProps &
  Pick<ResourceContext, 'selectResource'>;

const MetricsTabContent = ({
  details,
  selectResource,
}: MetricsTabContentProps): JSX.Element => {
  const endpoint = path(['links', 'endpoints', 'metrics'], details);

>>>>>>> centreon/dev-21.10.x
  const { sendRequest, sending } = useRequest<MetaServiceMetricListing>({
    decoder: metaServiceMetricListingDecoder,
    request: listMetaServiceMetrics,
  });

<<<<<<< HEAD
  const details = useAtomValue(detailsAtom);

  const endpoint = path(['links', 'endpoints', 'metrics'], details);

=======
>>>>>>> centreon/dev-21.10.x
  const sendListingRequest = ({
    atPage,
  }: {
    atPage?: number;
  }): Promise<MetaServiceMetricListing> => {
    return sendRequest({
      endpoint,
      parameters: {
        limit,
        page: atPage,
      },
    });
  };

  return (
    <InfiniteScroll
      details={details}
      limit={limit}
      loading={sending}
      loadingSkeleton={<LoadingSkeleton />}
      preventReloadWhen={details?.type !== 'metaservice'}
      sendListingRequest={sendListingRequest}
    >
      {({ infiniteScrollTriggerRef, entities }): JSX.Element => {
        return (
          <Metrics
            infiniteScrollTriggerRef={infiniteScrollTriggerRef}
            metrics={entities}
<<<<<<< HEAD
=======
            selectResource={selectResource}
>>>>>>> centreon/dev-21.10.x
          />
        );
      }}
    </InfiniteScroll>
  );
};

<<<<<<< HEAD
=======
const MemoizedMetricsTabContent = memoizeComponent<MetricsTabContentProps>({
  Component: MetricsTabContent,
  memoProps: ['details'],
});

const MetricsTab = ({ details }: TabProps): JSX.Element => {
  const { selectResource } = useResourceContext();

  return (
    <MemoizedMetricsTabContent
      details={details}
      selectResource={selectResource}
    />
  );
};

>>>>>>> centreon/dev-21.10.x
export default MetricsTab;
