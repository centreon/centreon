import { labelServiceConfiguration } from '../../translatedLabels';
import type { FormSection } from './models';

// The legacy "General Information" tab: name, host, template, check command,
// macros, scheduling and status. Its fields land in MON-213082 and MON-213083.
export const serviceConfiguration: FormSection = {
  defaultValues: {},
  detailDecoders: {},
  getInputs: () => [],
  getSchema: () => ({}),
  label: labelServiceConfiguration,
  toPayload: () => ({})
};
