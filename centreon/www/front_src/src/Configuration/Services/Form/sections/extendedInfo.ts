import { labelServiceExtendedInfo } from '../../translatedLabels';
import type { FormSection } from './models';

// The legacy "Extended Info" tab. Its fields land in MON-213087.
export const extendedInfo: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  label: labelServiceExtendedInfo,
  toPayload: () => ({})
};
