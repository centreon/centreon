import { useAtomValue, useSetAtom } from 'jotai';

import { useRequest } from '@centreon/ui';
import type { ListingModel } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { listResources } from '../../../Listing/api';
import { Resource } from '../../../models';
import { detailsAtom, selectResourceDerivedAtom } from '../../detailsAtoms';
import InfiniteScroll from '../../InfiniteScroll';

import ServiceList from './List';
import LoadingSkeleton from './LoadingSkeleton';
import { has } from 'ramda';

const ServicesTab = (): JSX.Element => {
  const { sendRequest, sending } = useRequest({
    request: listResources
  });

  const details = useAtomValue(detailsAtom);
  const platform = useAtomValue(platformVersionsAtom);

  const selectResource = useSetAtom(selectResourceDerivedAtom);

  const limit = 30;

  const sendListingRequest = ({
    atPage
  }: {
    atPage?: number;
  }): Promise<ListingModel<Resource>> => {
    const resourceTypes = has('centreon-anomaly-detection', platform?.modules)
      ? ['service', 'anomaly-detection']
      : ['service'];

    return sendRequest({
      limit,
      page: atPage,
      resourceTypes,
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

  return (
    <InfiniteScroll<Resource>
      details={details}
      limit={limit}
      loading={sending}
      loadingSkeleton={<LoadingSkeleton />}
      preventReloadWhen={details?.type !== 'host'}
      sendListingRequest={sendListingRequest}
    >
      {({ infiniteScrollTriggerRef, entities }): JSX.Element => {
        return (
          <ServiceList
            infiniteScrollTriggerRef={infiniteScrollTriggerRef}
            services={entities}
            onSelectService={selectResource}
          />
        );
      }}
    </InfiniteScroll>
  );
};

export default ServicesTab;
