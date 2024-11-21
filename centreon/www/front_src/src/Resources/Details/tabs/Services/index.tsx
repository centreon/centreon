import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isEmpty, isNil, map, pipe, reject, replace } from 'ramda';

import { useRequest } from '@centreon/ui';
import type { ListingModel } from '@centreon/ui';

import { listResources } from '../../../Listing/api';
import { Resource, ResourceCategory, ResourceType } from '../../../models';
import InfiniteScroll from '../../InfiniteScroll';
import { detailsAtom, selectResourceDerivedAtom } from '../../detailsAtoms';
import { platformVersionsAtom } from '../../../../Main/atoms/platformVersionsAtom';

import ServiceList from './List';
import LoadingSkeleton from './LoadingSkeleton';

type ResourceTypes = Array<keyof typeof ResourceCategory>;

const ServicesTab = (): JSX.Element => {
  const { sendRequest, sending } = useRequest({
    request: listResources
  });

  const details = useAtomValue(detailsAtom);
  const platformVersions = useAtomValue(platformVersionsAtom);

  const selectResource = useSetAtom(selectResourceDerivedAtom);

  const installedModules = platformVersions?.modules
    ? Object.keys(platformVersions?.modules)
    : [];

  const getResourceTypes = (): ResourceTypes => {
    const suffix = 'centreon-';
    const defaultResourceType = ['service'] as ResourceTypes;

    const resourceTypes = pipe(
      map((module: string) => {
        const key = replace(suffix, '', module);

        return equals(ResourceCategory[key], ResourceType.service) ? key : null;
      }),
      reject(isNil)
    )(installedModules);

    return !isEmpty(resourceTypes)
      ? [...defaultResourceType, ...(resourceTypes as ResourceTypes)]
      : defaultResourceType;
  };

  const limit = 30;

  const sendListingRequest = ({
    atPage
  }: {
    atPage?: number;
  }): Promise<ListingModel<Resource>> => {
    return sendRequest({
      limit,
      page: atPage,
      resourceTypes: getResourceTypes(),
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
