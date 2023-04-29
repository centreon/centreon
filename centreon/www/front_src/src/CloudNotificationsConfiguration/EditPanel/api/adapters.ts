import { map } from 'ramda';

export const adaptNotification = ({
  businessViews,
  hostGroups,
  id,
  isActivated,
  messages,
  name,
  serviceGroups,
  timeperiod,
  users
}: any): any => ({
  isActivated,
  messages: {
    channel: messages.channel?.label,
    message: messages.message,
    subject: messages.subject
  },
  name,
  resources: [
    {
      events: businessViews.events,
      ids: map((elm) => elm.id)(businessViews.ids),
      type: businessViews.type
    },
    {
      events: hostGroups.events,
      extra: {
        eventsServices: hostGroups?.extra?.eventsServices
      },
      ids: map((elm) => elm.id)(hostGroups.ids),
      type: hostGroups.type
    },
    {
      events: serviceGroups.events,
      ids: map((elm) => elm.id)(hostGroups.ids),
      type: serviceGroups.type
    }
  ],
  timeperiod: 1,
  users: map((elm) => elm.id)(users)
});
