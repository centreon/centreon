import { equals, isNil } from 'ramda';

import MailIcon from '@mui/icons-material/LocalPostOfficeOutlined';

import { ChannelsEnum, TimeperiodType } from '../models';

import { EventsType, MessageType } from './models';

const emptyEmail =
  '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}';

const hostEvents = [EventsType.up, EventsType.down, EventsType.unreachable];
const serviceEvents = [
  EventsType.ok,
  EventsType.warning,
  EventsType.critical,
  EventsType.unkown
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

const formatMessages = ({ channel, message, subject }: MessageType): object => {
  return {
    channel: {
      Icon: MailIcon,
      checked: !!equals(ChannelsEnum.Email, channel),
      label: ChannelsEnum.Email
    },
    message,
    subject
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
          checked: false,
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
