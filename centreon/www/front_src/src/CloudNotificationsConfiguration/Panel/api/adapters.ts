import { always, cond, equals, map, pipe, prop, sum, T } from 'ramda';

import { EventsType } from '../models';

const getBinaryEquivalence = cond([
  [equals(EventsType.Up), always(1)],
  [equals(EventsType.Down), always(2)],
  [equals(EventsType.Unreachable), always(4)],
  [equals(EventsType.Ok), always(1)],
  [equals(EventsType.Warning), always(2)],
  [equals(EventsType.Critical), always(4)],
  [equals(EventsType.Unknown), always(8)],
  [T, always(0)]
]);

export const adaptEvents = (events: Array<EventsType>): number => {
  return pipe(
    map((item: EventsType) => getBinaryEquivalence(item)),
    sum
  )(events);
};

interface AdaptNotificationGroup {
  events: Array<EventsType>;
  extra?: { eventsServices: Array<EventsType> };
  ids: Array<{ id: number }>;
  type: string;
}

interface AdaptNotificationInput {
  businessviews?: AdaptNotificationGroup;
  contactgroups: Array<{ id: number }>;
  hostGroups: AdaptNotificationGroup;
  isActivated: boolean;
  messages: {
    channel?: { label?: string };
    formattedMessage: string;
    message: string;
    subject: string;
  };
  name: string;
  serviceGroups: AdaptNotificationGroup;
  timeperiod: { id: number };
  users: Array<{ id: number }>;
}

export const adaptNotification = ({
  hostGroups,
  isActivated,
  messages,
  name,
  serviceGroups,
  users,
  contactgroups,
  businessviews,
  timeperiod
}: AdaptNotificationInput): object => ({
  contactgroups: map(prop('id'), contactgroups),
  is_activated: isActivated,
  messages: [
    {
      channel: messages.channel?.label,
      formatted_message: messages.formattedMessage,
      message: messages.message,
      subject: messages.subject
    }
  ],
  name,
  resources: [
    {
      events: adaptEvents(hostGroups.events),
      extra: {
        event_services: adaptEvents(
          (hostGroups?.extra?.eventsServices ?? []) as Array<EventsType>
        )
      },
      ids: map(prop('id'), hostGroups.ids),
      type: hostGroups.type
    },
    {
      events: adaptEvents(serviceGroups.events),
      ids: map(prop('id'), serviceGroups.ids),
      type: serviceGroups.type
    },
    ...(businessviews
      ? [
          {
            events: adaptEvents(businessviews.events),
            ids: map(prop('id'), businessviews.ids),
            type: businessviews.type
          }
        ]
      : [])
  ],
  timeperiod_id: timeperiod.id,
  users: map(prop('id'), users)
});
