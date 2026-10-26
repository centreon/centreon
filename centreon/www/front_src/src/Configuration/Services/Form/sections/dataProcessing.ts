import { labelDataProcessing } from '../../translatedLabels';
import type { FormSection } from './models';

// The legacy "Data Processing" tab. Its fields land in MON-213085.
export const dataProcessing: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  label: labelDataProcessing,
  toPayload: () => ({})
};
