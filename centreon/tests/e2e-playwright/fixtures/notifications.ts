import type { ClapiAction } from '../helpers/CentreonApi';
import { hostGroupActions } from './monitoring';

/**
 * Notification fixtures for the Cloud-notifications listing spec, mirroring
 * `tests/e2e/fixtures/notifications/notification-creation.json` and the
 * Cypress `Cloud-notifications/05-notification-listing` setup.
 */

export interface NotificationBody {
  name: string;
  is_activated: boolean;
  contactgroups: Array<number>;
  messages: Array<{
    channel: string;
    subject: string;
    formatted_message: string;
    message: string;
  }>;
  resources: Array<{
    type: string;
    ids: Array<number>;
    events: number;
    extra?: { event_services: number };
  }>;
  timeperiod_id: number;
  users: Array<number>;
}

/** Host group referenced by every notification rule created in the spec. */
export const notificationHostGroupName = 'notification_host_group';

export const notificationHostGroupActions: Array<ClapiAction> =
  hostGroupActions(notificationHostGroupName);

/**
 * Build a notification rule body bound to a given host group id. The Lexical
 * editor state mirrors the JSON fixture used by the Cypress suite.
 */
export const buildNotification = (
  name: string,
  hostGroupId: number
): NotificationBody => ({
  contactgroups: [],
  is_activated: true,
  messages: [
    {
      channel: 'Email',
      formatted_message:
        '<p dir="ltr"><span style="white-space: pre-wrap;">Notification Body</span></p>',
      message:
        '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Notification Body","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}',
      subject: 'Notification Subject'
    }
  ],
  name,
  resources: [
    {
      events: 7,
      extra: { event_services: 15 },
      ids: [hostGroupId],
      type: 'hostgroup'
    }
  ],
  timeperiod_id: 1,
  users: [1]
});
