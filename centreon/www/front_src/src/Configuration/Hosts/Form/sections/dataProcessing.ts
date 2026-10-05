import { labelDataProcessing } from '../../translatedLabels';
import type { FormSection } from './models';

export const dataProcessing: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  label: labelDataProcessing,
  toPayload: () => ({})
};
