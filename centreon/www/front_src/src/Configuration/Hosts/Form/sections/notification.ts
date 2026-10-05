import { labelNotification } from '../../translatedLabels';
import type { FormSection } from './models';

export const notification: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  // Notifications are an onPrem concern; the US has no such tab on cloud.
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  label: labelNotification,
  toPayload: () => ({})
};
