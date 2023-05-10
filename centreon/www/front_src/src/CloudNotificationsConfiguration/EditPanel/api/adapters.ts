import { T, always, cond, equals, map, pipe, sum } from 'ramda';

import { EventsType } from '../models';

const adpatIds = (data: Array<{ id: number; name: string }>): Array<number> =>
  map(({ id }) => id)(data);

const getBinaryEquivalence = cond([
  [equals(EventsType.up), always(1)],
  [equals(EventsType.down), always(2)],
  [equals(EventsType.unreachable), always(4)],
  [equals(EventsType.ok), always(1)],
  [equals(EventsType.warning), always(2)],
  [equals(EventsType.critical), always(4)],
  [equals(EventsType.unkown), always(8)],
  [T, always(0)]
]);

const adaptEvents = (data: Array<string>): number => {
  return pipe(
    map((item) => getBinaryEquivalence(item)),
    sum
  )(data);
};

export const adaptNotifications = ({
  // businessViews,
  hostGroups,
  isActivated,
  messages,
  name,
  serviceGroups,
  users
}): object => ({
  is_activated: isActivated,
  messages: {
    channel: messages.channel?.label,
    message: messages.message,
    subject: messages.subject
  },
  name,
  resources: [
    // {
    //   events: businessViews.events,
    //   ids: map(({ id }) => id)(businessViews.ids),
    //   type: businessViews.type
    // },
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
      ids: adpatIds(hostGroups.ids),
      type: serviceGroups.type
    }
  ],
  timeperiod: 1,
  users: adpatIds(users)
});
