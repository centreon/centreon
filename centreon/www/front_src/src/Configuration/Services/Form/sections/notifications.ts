import { labelNotifications } from '../../translatedLabels';
import type { FormSection } from './models';

// The legacy "Notifications" tab. Its fields land in MON-213086.
export const notifications: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  // The tab does not exist on cloud, where notifications keep their defaults.
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  label: labelNotifications,
  toPayload: () => ({})
};
