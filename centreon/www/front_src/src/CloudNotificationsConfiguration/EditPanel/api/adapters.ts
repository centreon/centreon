import { T, always, cond, equals, map, pipe, sum } from 'ramda';

import { EventsType } from '../models';

const adpatIds = (data: Array<{ id: number; name: string }>): Array<number> =>
  map(({ id }) => id)(data);

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

const adaptEvents = (data: Array<string>): number => {
  return pipe(
    map((item) => getBinaryEquivalence(item)),
    sum
  )(data);
};

export const adaptNotifications = ({
  hostGroups,
  isActivated,
  messages,
  name,
  serviceGroups,
  users
}): object => ({
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
        eventsServices: adaptEvents(hostGroups?.extra?.eventsServices)
      },
      ids: adpatIds(hostGroups.ids),
      type: hostGroups.type
    },
    {
      events: adaptEvents(serviceGroups.events),
      ids: adpatIds(serviceGroups.ids),
      type: serviceGroups.type
    }
  ],
  timeperiod: 1,
  users: adpatIds(users)
});
