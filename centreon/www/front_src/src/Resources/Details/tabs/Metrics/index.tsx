import { useAtomValue } from 'jotai';
import { path } from 'ramda';

import { useRequest } from '@centreon/ui';

import InfiniteScroll from '../../InfiniteScroll';
import { detailsAtom } from '../../detailsAtoms';
import LoadingSkeleton from '../Services/LoadingSkeleton';

import Metrics from './Metrics';
import { listMetaServiceMetrics } from './api';
import { metaServiceMetricListingDecoder } from './api/decoders';
import { MetaServiceMetricListing } from './models';

const limit = 30;

const MetricsTab = (): JSX.Element => {
  const { sendRequest, sending } = useRequest<MetaServiceMetricListing>({
    decoder: metaServiceMetricListingDecoder,
    request: listMetaServiceMetrics
  });

  const details = useAtomValue(detailsAtom);

  const endpoint = path(['links', 'endpoints', 'metrics'], details);

  const sendListingRequest = ({
    atPage
  }: {
    atPage?: number;
  }): Promise<MetaServiceMetricListing> => {
    return sendRequest({
      endpoint,
      parameters: {
        limit,
        page: atPage
      }
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
          />
        );
      }}
    </InfiniteScroll>
  );
};

export default MetricsTab;
