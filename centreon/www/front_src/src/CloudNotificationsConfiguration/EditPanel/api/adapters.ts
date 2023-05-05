import { map } from 'ramda';

// import { NotificationType } from '../models';

export const adaptNotifications = ({
  // businessViews,
  hostGroups,
  isActivated,
  messages,
  name,
  serviceGroups,
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
    // {
    //   events: businessViews.events,
    //   ids: map(({ id }) => id)(businessViews.ids),
    //   type: businessViews.type
    // },
    {
      events: hostGroups.events,
      extra: {
        eventsServices: hostGroups?.extra?.eventsServices
      },
      ids: map(({ id }) => id)(hostGroups.ids),
      type: hostGroups.type
    },
    {
      events: serviceGroups.events,
      ids: map(({ id }) => id)(hostGroups.ids),
      type: serviceGroups.type
    }
  ],
  timeperiod: 1,
  users: map(({ id }) => id)(users)
});
