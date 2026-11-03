import type { FormValues } from '../../ResourceForm/models';

// The form key of the service's one direct template (F03), which the template
// block (MON-213082) fills with `{ id, name } | null`. The check command and
// the macros read it: a template brings a command and macros of its own.
export const serviceTemplateKey = 'template';

// The server refuses a template chain without a command, so a template always
// brings one.
export const hasServiceTemplate = (values: FormValues): boolean =>
  !!values[serviceTemplateKey];
