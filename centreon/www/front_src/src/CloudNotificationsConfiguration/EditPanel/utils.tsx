import { equals, isNil } from 'ramda';

import { ChannelsEnum, ResourcesTypeEnum } from '../models';

import { EmailIcon } from './Channel/Icons';
import { EventsType } from './models';

const emptyEmail =
  '{"root":{"children":[{"children":[],"direction":null,"format":"","indent":0,"type":"paragraph","version":1}],"direction":null,"format":"","indent":0,"type":"root","version":1}}';

const hostEvents = [EventsType.Up, EventsType.Down, EventsType.Unreachable];
const serviceEvents = [
  EventsType.Ok,
  EventsType.Warning,
  EventsType.Critical,
  EventsType.Unkown
];

const formatEvents = (
  event: number,
  allEvents: Array<EventsType>
): Array<EventsType> => {
  const events: Array<EventsType> = [];
  let value = event;

  for (let i = 0; i < allEvents.length + 1; i += 1) {
    if (value % 2 === 1) {
      events.push(allEvents[i]);
    }
    value = Math.floor(value / 2);
  }

  return events;
};

const formatMessages = ({ messages, messageType }): object => {
  const message = messages.find((elm) => equals(elm.channel, messageType));

  return {
    channel: {
      Icon: EmailIcon,
      checked: true,
      label: ChannelsEnum.Email
    },
    message: message.message,
    subject: message.subject
  };
};

const formatResource = ({ resources, resourceType }): object => {
  const resource = resources.find((elm) => equals(elm.type, resourceType));
  const events = equals(resourceType, ResourcesTypeEnum.HG)
    ? hostEvents
    : serviceEvents;

  return {
    ...resource,
    events: formatEvents(resource.events, events),
    extra: {
      eventsServices: formatEvents(
        resource?.extra ? resource.extra.eventsServices : 0,
        serviceEvents
      ),
      includeServices: {
        checked: !isNil(resource?.extra),
        label: 'Include services for these hosts'
      }
    }
  };
};

export {
  emptyEmail,
  hostEvents,
  serviceEvents,
  formatMessages,
  formatResource
};
