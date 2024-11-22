import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import {
  ChannelsEnum,
  NotificationsType,
  ResourcesType,
  ResourcesTypeEnum,
  TimeperiodType
} from '../../models';

const timeperiod = JsonDecoder.object<TimeperiodType>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Timeperiod'
);

const resource = JsonDecoder.object<ResourcesType>(
  {
    count: JsonDecoder.number,
    type: JsonDecoder.enumeration(ResourcesTypeEnum, 'type')
  },
  'Resource'
);

const notificationlistingDecoder = JsonDecoder.object<NotificationsType>(
  {
    channels: JsonDecoder.array(
      JsonDecoder.enumeration(ChannelsEnum, 'chennels'),
      'channels'
    ),
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string,
    resources: JsonDecoder.array(resource, 'Resources'),
    timeperiod,
    userCount: JsonDecoder.number
  },
  'Notifications Listing',
  {
    channels: 'channels',
    id: 'id',
    isActivated: 'is_activated',
    name: 'name',
    resources: 'resources',
    timeperiod: 'timeperiod',
    userCount: 'user_count'
  }
);

export const listingDecoder = buildListingDecoder<NotificationsType>({
  entityDecoder: notificationlistingDecoder,
  entityDecoderName: 'Notifications',
  listingDecoderName: 'NotificationsListing'
});
