import type { FormikValues } from 'formik';

// Where the macros a resource inherits come from: its templates, fetched one
// by one, and its own command.
export interface InheritedMacrosConfig {
  // Base endpoint `getTemplateMacrosEndpoint` is relative to.
  baseEndpoint: string;
  // The command picked in the form, null without one.
  getCommandId: (values: FormikValues) => number | null;
  // One template's macros, its own and those it inherits. The response is
  // decoded with `templateMacrosDecoder`.
  getTemplateMacrosEndpoint: (params: { id: number }) => string;
  // The templates picked in the form, in order.
  getTemplateIds: (values: FormikValues) => Array<number>;
  // TanStack Query key prefix of one template's macros.
  queryKey: string;
}

export interface MacrosSchemaConfig {
  // The stored name is `<prefix><NAME>$`: `$_HOST` or `$_SERVICE`.
  prefix: string;
  // Stored names the server reserves, as `$_SERVICECRITICALITY_LEVEL$`.
  // Refused here; none leaves them to the server.
  reservedNames?: Array<string>;
}
