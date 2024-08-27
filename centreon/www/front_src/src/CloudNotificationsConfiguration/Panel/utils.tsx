import { equals, isNil } from 'ramda';

import { ChannelsEnum, ResourcesTypeEnum } from '../models';
import { labelIncludeServicesForTheseHosts } from '../translatedLabels';

import { EmailIcon } from './FormInputs/Channel/Icons';
import { EventsType } from './models';

const emptyEmail =
  '{"root":{"children":[{"children":[],"direction":null,"format":"","indent":0,"type":"paragraph","version":1,"textFormat":0,"textStyle":""}],"direction":null,"format":"","indent":0,"type":"root","version":1}}';
const defaultEmailBody =
  '{"root":{"children":[{"children":[{"detail":0,"format":1,"mode":"normal","style":"","text":"Centreon notification","type":"text","version":1},{"type":"linebreak","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"Notification Type: ","type":"text","version":1},{"detail":0,"format":1,"mode":"normal","style":"","text":"{{NOTIFICATIONTYPE}}","type":"text","version":1},{"type":"linebreak","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"Resource: {{NAME}}","type":"text","version":1},{"type":"linebreak","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"State: ","type":"text","version":1},{"detail":0,"format":1,"mode":"normal","style":"","text":"{{STATE}}","type":"text","version":1},{"type":"linebreak","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"Date/Time: {{SHORTDATETIME}}","type":"text","version":1},{"type":"linebreak","version":1},{"type":"linebreak","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"Additional Info: {{OUTPUT}}","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}';
const defaultEmailSubject =
  '{{NOTIFICATIONTYPE}} alert - {{NAME}} is {{STATE}}';

const hostEvents = [EventsType.Up, EventsType.Down, EventsType.Unreachable];

const serviceEvents = [
  EventsType.Ok,
  EventsType.Warning,
  EventsType.Critical,
  EventsType.Unknown
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

interface FormatResourceType {
  resourceType;
  resources;
  t?;
}

const formatResource = ({
  resources,
  resourceType,
  t
}: FormatResourceType): object => {
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
        label: t?.(labelIncludeServicesForTheseHosts)
      }
    }
  };
};

export {
  emptyEmail,
  defaultEmailBody,
  defaultEmailSubject,
  hostEvents,
  serviceEvents,
  formatMessages,
  formatResource
};
