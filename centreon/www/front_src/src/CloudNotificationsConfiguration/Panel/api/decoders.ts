import { JsonDecoder } from 'ts.data.json';

import { TimeperiodType, ChannelsEnum, ResourcesTypeEnum } from '../../models';
import {
  MessageType,
  NamedEntity,
  NotificationType,
  ResourceType,
  UserType,
  ResourceIdsType,
  ResourceExtraType,
  MessageType
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
    eventsServices: JsonDecoder.number
  },
  'Resource Extra Type',
  {
    eventsServices: 'event_services'
  }
);

const resource = JsonDecoder.object<ResourceType>(
  {
    events: JsonDecoder.number,
    extra: JsonDecoder.optional(resourceExtraType),
    ids: JsonDecoder.array(ResourceId, 'Ids'),
    type: JsonDecoder.enumeration(ResourcesTypeEnum, 'Type')
  },
  'Resource'
);

const user = JsonDecoder.object<UserType>(
  {
    id: JsonDecoder.number,
    alias: JsonDecoder.string
  },
  'User'
);

const contactGroup = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'ContactGroup'
);

const message = JsonDecoder.object<MessageType>(
  {
    channel: JsonDecoder.enumeration(ChannelsEnum, 'Channel'),
    formattedMessage: JsonDecoder.string,
    message: JsonDecoder.string,
    subject: JsonDecoder.string
  },
  'Message',
  {
    formattedMessage: 'formatted_message'
  }
);

export const notificationdecoder = JsonDecoder.object<NotificationType>(
  {
    contactgroups: JsonDecoder.array(contactGroup, 'Contactgroups'),
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    messages: JsonDecoder.array(message, 'Messages'),
    name: JsonDecoder.string,
    resources: JsonDecoder.array(resource, 'Resources'),
    timeperiod,
    users: JsonDecoder.array(user, 'Users')
  },
  'Notification Listing',
  {
    isActivated: 'is_activated'
  }
);
