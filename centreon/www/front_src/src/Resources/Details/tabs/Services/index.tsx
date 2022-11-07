<<<<<<< HEAD
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import { useRequest, ListingModel } from '@centreon/ui';

import { listResources } from '../../../Listing/api';
import { Resource } from '../../../models';
import InfiniteScroll from '../../InfiniteScroll';
import { detailsAtom, selectResourceDerivedAtom } from '../../detailsAtoms';
=======
import * as React from 'react';

import { useRequest, ListingModel } from '@centreon/ui';

import { TabProps } from '..';
import { ResourceContext, useResourceContext } from '../../../Context';
import { listResources } from '../../../Listing/api';
import { Resource } from '../../../models';
import InfiniteScroll from '../../InfiniteScroll';
import memoizeComponent from '../../../memoizedComponent';
>>>>>>> centreon/dev-21.10.x

import ServiceList from './List';
import LoadingSkeleton from './LoadingSkeleton';

<<<<<<< HEAD
const ServicesTab = (): JSX.Element => {
=======
type ServicesTabContentProps = TabProps &
  Pick<
    ResourceContext,
    'selectResource' | 'tabParameters' | 'setServicesTabParameters'
  >;

const ServicesTabContent = ({
  details,
  selectResource,
}: ServicesTabContentProps): JSX.Element => {
>>>>>>> centreon/dev-21.10.x
  const { sendRequest, sending } = useRequest({
    request: listResources,
  });

<<<<<<< HEAD
  const details = useAtomValue(detailsAtom);
  const selectResource = useUpdateAtom(selectResourceDerivedAtom);

=======
>>>>>>> centreon/dev-21.10.x
  const limit = 30;

  const sendListingRequest = ({
    atPage,
  }: {
    atPage?: number;
  }): Promise<ListingModel<Resource>> => {
    return sendRequest({
      limit,
      page: atPage,
      resourceTypes: ['service'],
      search: {
        conditions: [
          {
            field: 'h.name',
            values: {
              $eq: details?.name,
            },
          },
        ],
      },
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

<<<<<<< HEAD
=======
const MemoizedServiceTabContent = memoizeComponent<ServicesTabContentProps>({
  Component: ServicesTabContent,
  memoProps: ['details', 'tabParameters'],
});

const ServicesTab = ({ details }: TabProps): JSX.Element => {
  const { selectResource, tabParameters, setServicesTabParameters } =
    useResourceContext();

  return (
    <MemoizedServiceTabContent
      details={details}
      selectResource={selectResource}
      setServicesTabParameters={setServicesTabParameters}
      tabParameters={tabParameters}
    />
  );
};

>>>>>>> centreon/dev-21.10.x
export default ServicesTab;
