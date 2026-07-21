import { map, pipe, prop } from 'ramda';

import { ResourceType } from '../../Panel/models';

interface AdaptNotificationInput {
  contactgroups: Array<{ id: number }>;
  isActivated: boolean;
  messages: Array<{ formattedMessage: string } & Record<string, unknown>>;
  name: string;
  resources: Array<ResourceType>;
  users: Array<{ id: number }>;
}

export const adaptNotification = ({
  isActivated,
  messages,
  name,
  resources,
  users,
  contactgroups
}: AdaptNotificationInput): object => ({
  contactgroups: map(prop('id'), contactgroups),
  is_activated: isActivated,
  messages: [
    {
      ...messages[0],
      formatted_message: messages[0].formattedMessage
    }
  ],
  name,
  resources: pipe(
    map((resource: ResourceType) => ({
      ...resource,
      ids: map(prop('id'), resource.ids)
    })),
    map((resource) =>
      resource?.extra
        ? {
            ...resource,
            extra: { event_services: resource.extra.eventsServices }
          }
        : resource
    )
  )(resources),
  timeperiod_id: 1,
  users: map(prop('id'), users)
});
