import { buildListingDecoder, InputType, type SelectEntry } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';

import {
  namedEntityDecoder,
  optionalNamedEntityDecoder
} from '../api/decoders';
import type { FieldBlock, NamedEntity, ResourceOptions } from '../models';
import { buildSelector } from '../selector';
import { labelSeverityWithLevel } from '../translatedLabels';

// The level is read only when the severity is shown with it.
export type Severity = NamedEntity & { level?: number | null };

const severityDecoder = JsonDecoder.object<Severity>(
  {
    ...namedEntityDecoder,
    level: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.number))
  },
  'Severity'
);

const severitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: severityDecoder,
  entityDecoderName: 'Severity',
  listingDecoderName: 'Severity List'
});

// `name (level)`, as the legacy select shows a severity; the name alone when
// the API sends no level.
const getSeverityLabel =
  (t: TFunction) =>
  (option: unknown): string => {
    const severity = option as Severity | null;

    if (!severity) {
      return '';
    }

    return severity.level === undefined || severity.level === null
      ? severity.name
      : t(labelSeverityWithLevel, {
          level: severity.level,
          name: severity.name
        });
  };

// A field of the resource itself, not of its extended informations.
export const getSeverityBlock = ({
  endpoint,
  label,
  resource: { baseEndpoint, idPrefix },
  withLevel = false
}: {
  endpoint: string;
  // Untranslated.
  label: string;
  resource: ResourceOptions;
  // Each option and the picked value read `name (level)`.
  withLevel?: boolean;
}): FieldBlock<{ severity: Severity | null }> => ({
  defaultValues: { severity: null },
  detailDecoders: {
    // Left out too when the user's ACLs do not cover it.
    severity: withLevel
      ? JsonDecoder.optional(JsonDecoder.nullable(severityDecoder)).map(
          (value) => value ?? null
        )
      : optionalNamedEntityDecoder('Severity')
  },
  getInputs: ({ t }) => ({
    severity: {
      connectedAutocomplete: buildSelector({
        baseEndpoint,
        endpoint,
        queryKey: `${idPrefix}-severity`,
        ...(withLevel
          ? {
              decoder: severitiesListDecoder,
              // The options and the picked value alike.
              getOptionLabel: getSeverityLabel(t),
              getRenderedOptionText: getSeverityLabel(t)
            }
          : { getOptionLabel: (option) => (option as SelectEntry)?.name })
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
