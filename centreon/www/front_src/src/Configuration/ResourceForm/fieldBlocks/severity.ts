import { InputType, type SelectEntry } from '@centreon/ui';

import { optionalNamedEntityDecoder } from '../api/decoders';
import type { FieldBlock, NamedEntity, ResourceOptions } from '../models';
import { buildSelector } from '../selector';

// A field of the resource itself, not of its extended informations.
export const severity = ({
  endpoint,
  label,
  resource: { baseEndpoint, idPrefix }
}: {
  endpoint: string;
  // Untranslated.
  label: string;
  resource: ResourceOptions;
}): FieldBlock<{ severity: NamedEntity | null }> => ({
  defaultValues: { severity: null },
  detailDecoders: {
    // Left out too when the user's ACLs do not cover it.
    severity: optionalNamedEntityDecoder('Severity')
  },
  getInputs: ({ t }) => ({
    severity: {
      connectedAutocomplete: buildSelector({
        baseEndpoint,
        endpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: `${idPrefix}-severity`
      }),
      dataTestId: `${idPrefix}-severity`,
      fieldName: 'severity',
      label: t(label),
      type: InputType.SingleConnectedAutocomplete
    }
  }),
  getSchema: () => ({}),
  toPayload: (values) => ({
    severity_id: (values.severity as NamedEntity | null | undefined)?.id ?? null
  })
});
