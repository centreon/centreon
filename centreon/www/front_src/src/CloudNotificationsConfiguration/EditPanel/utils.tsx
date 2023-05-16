import { equals, isNil } from 'ramda';

import MailIcon from '@mui/icons-material/LocalPostOfficeOutlined';

import { ChannelsEnum, TimeperiodType } from '../models';

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

const formatEntityNamed = ({
  name
}: TimeperiodType): { checked: boolean; label: string } => {
  return {
    checked: true,
    label: name
  };
};

const formatMessages = ({ messages, messageType }): object => {
  const message = messages.find((elm) => equals(elm.channel, messageType));

  return {
    channel: {
      Icon: MailIcon,
      checked: true,
      label: ChannelsEnum.Email
    },
    message: message.message,
    subject: message.subject
  };
};

const formatResource = ({ resources, resourceType }): object => {
  const resource = resources.find((elm) => equals(elm.type, resourceType));

  if (!isNil(resource?.extra)) {
    return {
      ...resource,
      events: formatEvents(resource.events, hostEvents),
      extra: {
        eventsServices: formatEvents(
          resource.extra.eventsServices,
          serviceEvents
        ),
        includeServices: {
          checked: true,
          label: 'Include services for these hosts'
        }
      }
    };
  }

  return { ...resource, events: formatEvents(resource.events, serviceEvents) };
};

export {
  emptyEmail,
  hostEvents,
  serviceEvents,
  formatEntityNamed,
  formatMessages,
  formatResource
};
