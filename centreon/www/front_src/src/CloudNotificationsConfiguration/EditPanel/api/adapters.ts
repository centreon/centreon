import { T, always, cond, equals, map, pipe, prop, sum } from 'ramda';

import { EventsType } from '../models';

const getBinaryEquivalence = cond([
  [equals(EventsType.Up), always(1)],
  [equals(EventsType.Down), always(2)],
  [equals(EventsType.Unreachable), always(4)],
  [equals(EventsType.Ok), always(1)],
  [equals(EventsType.Warning), always(2)],
  [equals(EventsType.Critical), always(4)],
  [equals(EventsType.Unkown), always(8)],
  [T, always(0)]
]);

export const adaptEvents = (events: Array<EventsType>): number => {
  return pipe(
    map((item: EventsType) => getBinaryEquivalence(item)),
    sum
  )(events);
};

export const adaptNotification = ({
  hostGroups,
  isActivated,
  messages,
  name,
  serviceGroups,
  users,
  contactgroups
}): object => ({
  contactgroups: map(prop('id'), contactgroups),
  is_activated: isActivated,
  messages: [
    {
      channel: messages.channel?.label,
      message: messages.message,
      subject: messages.subject
    }
  ],
  name,
  resources: [
    {
      events: adaptEvents(hostGroups.events),
      extra: {
        event_services: adaptEvents(hostGroups?.extra?.eventsServices)
      },
      ids: map(prop('id'), hostGroups.ids),
      type: hostGroups.type
    },
    {
      events: adaptEvents(serviceGroups.events),
      ids: map(prop('id'), serviceGroups.ids),
      type: serviceGroups.type
    }
  ],
  timeperiod_id: 1,
  users: map(prop('id'), users)
});
