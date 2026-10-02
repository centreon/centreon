import { labelHostExtendedInfos } from '../../translatedLabels';
import type { FormSection } from './models';

export const extendedInfos: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  label: labelHostExtendedInfos,
  toPayload: () => ({})
};
