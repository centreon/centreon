import { map, pipe, prop } from 'ramda';

import { ResourceType } from '../../Panel/models';

export const adaptNotification = ({
  isActivated,
  messages,
  name,
  resources,
  users,
  contactgroups
}): object => ({
  contactgroups: map(prop('id'), contactgroups),
  is_activated: isActivated,
  messages,
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
