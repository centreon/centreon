import { CancelToken } from 'axios';

import {
  buildListingEndpoint,
  ListingModel,
  getData,
  ListingParameters,
} from '@centreon/ui';

import { TimelineEvent } from '../models';

interface ListTimeLineEventsProps {
  endpoint: string;
  parameters: ListingParameters;
}

const buildListTimelineEventsEndpoint = ({
  endpoint,
  parameters,
}: ListTimeLineEventsProps): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters,
  });

const listTimelineEvents =
  (cancelToken: CancelToken) =>
  ({
    endpoint,
    parameters,
  }: ListTimeLineEventsProps): Promise<ListingModel<TimelineEvent>> => {
<<<<<<< HEAD
    return getData<ListingModel<TimelineEvent>>(cancelToken)({
      endpoint: buildListTimelineEventsEndpoint({ endpoint, parameters }),
    });
=======
    return getData<ListingModel<TimelineEvent>>(cancelToken)(
      buildListTimelineEventsEndpoint({ endpoint, parameters }),
    );
>>>>>>> centreon/dev-21.10.x
  };

export { listTimelineEvents, buildListTimelineEventsEndpoint };
