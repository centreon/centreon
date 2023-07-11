import { baseEndpoint } from '../../api/endpoint';

export const deleteSingleNotificationEndpoint = (id): string => {
  return `${baseEndpoint}/configuration/notifications/${id}`;
};
export const deleteMultipleNotificationEndpoint = `${baseEndpoint}/configuration/notifications/_delete`;
