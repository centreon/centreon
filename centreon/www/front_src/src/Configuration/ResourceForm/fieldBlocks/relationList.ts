import { InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { type AnySchema, array } from 'yup';

import {
  buildNamedEntitiesListDecoder,
  namedEntitiesDecoder
} from '../api/decoders';
import type {
  BlockId,
  FieldBlock,
  NamedEntity,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../models';
import { buildSelector, toIds } from '../selector';
import { labelRequired } from '../translatedLabels';
import { withUpdateMode } from '../updateMode';

// A list of entities the resource is linked to: groups, categories, parents…
// It is a block of its own, and a list a mass change adds to or replaces.
export const getRelationListBlock = <Id extends BlockId>({
  decoderNames,
  detailKey,
  endpoint,
  entityDecoder,
  getSchema,
  id,
  isAvailable,
  isRequired,
  label,
  name,
  payloadKey,
  resource: { baseEndpoint, idPrefix }
}: {
  // As the decoder reports a failure: one entity, then the list.
  decoderNames: [entity: string, list: string];
  // The detail's key, when it is not the form's.
  detailKey?: string;
  endpoint: string;
  // How the detail and the selector read one entity, when it is not as a
  // plain `{ id, name }`: a SNMP trap is shown with its vendor.
  entityDecoder?: JsonDecoder.Decoder<NamedEntity>;
  // A rule of the resource's own on the list.
  getSchema?: (context: SectionContext) => AnySchema;
  // Its form key, its update mode field and the block it hands over.
  id: Id;
  // Where the list exists at all, such as on-premise only.
  isAvailable?: (context: PlatformContext) => boolean;
  // At least one entity is then required.
  isRequired?: (context: PlatformContext) => boolean;
  // Untranslated.
  label: string;
  // Kebab-case, for its test id and query key.
  name: string;
  payloadKey: string;
  resource: ResourceOptions;
}): FieldBlock<Record<Id, Array<NamedEntity>>> => {
  const isRequiredOn = (context: PlatformContext): boolean =>
    !context.isMassChange && !!isRequired?.(context);

  return {
    defaultValues: { [id]: [] },
    detailDecoders: {
      [id]: entityDecoder
        ? JsonDecoder.array(entityDecoder, decoderNames[1])
        : namedEntitiesDecoder(...decoderNames)
    } as FieldBlock<Record<Id, Array<NamedEntity>>>['detailDecoders'],
    ...(detailKey && {
      detailKeyMap: { [id]: detailKey } as FieldBlock<
        Record<Id, Array<NamedEntity>>
      >['detailKeyMap']
    }),
    getInputs: (context) => ({
      [id]: withUpdateMode({
        field: id,
        idPrefix,
        input: {
          connectedAutocomplete: buildSelector({
            baseEndpoint,
            chipColor: 'primary',
            ...(entityDecoder && {
              decoder: buildNamedEntitiesListDecoder(entityDecoder)
            }),
            endpoint,
            queryKey: `${idPrefix}-${name}`
          }),
          dataTestId: `${idPrefix}-${name}`,
          fieldName: id,
          ...(isRequired && { getRequired: () => isRequiredOn(context) }),
          label: context.t(label),
          type: InputType.MultiConnectedAutocomplete
        },
        isMassChange: context.isMassChange,
        t: context.t
      })
    }),
    // The required rule and the resource's own one both apply.
    getSchema: (context) => {
      const rules: Array<AnySchema> = [
        ...(isRequired
          ? [
              isRequiredOn(context)
                ? array().min(1, context.t(labelRequired))
                : array().notRequired()
            ]
          : []),
        ...(getSchema ? [getSchema(context)] : [])
      ];

      return rules.length
        ? { [id]: rules.reduce((merged, rule) => merged.concat(rule)) }
        : {};
    },
    ...(isAvailable && { isAvailable }),
    massChange: { updateModeFields: { [id]: [[payloadKey]] } },
    toPayload: (values) => ({
      [payloadKey]: toIds(values[id] as Array<NamedEntity> | null | undefined)
    })
  };
};
