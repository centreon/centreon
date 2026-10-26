import { labelRelations } from '../../translatedLabels';
import type { FormSection } from './models';

// The legacy "Relations" tab: service groups and traps. Its fields land in
// MON-213084.
export const relations: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  label: labelRelations,
  toPayload: () => ({})
};
