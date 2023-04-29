import { JsonDecoder } from 'ts.data.json';

import { TimeperiodType, ChannelsEnum, ResourcesTypeEnum } from '../../models';
import {
  NotificationType,
  ResourceType,
  UserType,
  ResourceIdsType,
  ResourceExtraType,
  MessageType,
  EventsType
} from '../models';

const timeperiod = JsonDecoder.object<TimeperiodType>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Timeperiod'
);

const ResourceId = JsonDecoder.object<ResourceIdsType>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'ResourceId'
);

const resourceExtraType = JsonDecoder.object<ResourceExtraType>(
  {
    eventsServices: JsonDecoder.array(
      JsonDecoder.enumeration(EventsType, 'Type'),
      'Events services'
    )
  },
  'Resource Extra Type',
  {
    eventsServices: 'events_services'
  }
);

const resource = JsonDecoder.object<ResourceType>(
  {
    events: JsonDecoder.array(
      JsonDecoder.enumeration(EventsType, 'Type'),
      'Events'
    ),
    extra: JsonDecoder.optional(resourceExtraType),
    ids: JsonDecoder.array(ResourceId, 'Ids'),
    type: JsonDecoder.enumeration(ResourcesTypeEnum, 'Type')
  },
  'Resource'
);

const user = JsonDecoder.object<UserType>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'User'
);

const message = JsonDecoder.object<MessageType>(
  {
    channel: JsonDecoder.enumeration(ChannelsEnum, 'Channel'),
    message: JsonDecoder.string,
    subject: JsonDecoder.string
  },
  'Message'
);

export const notificationdecoder = JsonDecoder.object<NotificationType>(
  {
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    messages: JsonDecoder.array(message, 'Messages'),
    name: JsonDecoder.string,
    resources: JsonDecoder.array(resource, 'Resources'),
    timeperiod,
    users: JsonDecoder.array(user, 'Users')
  },
  'Notifications Listing',
  {
    id: 'id',
    isActivated: 'is_activated',
    messages: 'messages',
    name: 'name',
    resources: 'resources',
    timeperiod: 'timeperiod',
    users: 'users'
  }
);
