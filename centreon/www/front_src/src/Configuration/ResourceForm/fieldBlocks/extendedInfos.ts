import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { object, string } from 'yup';

import { iconDecoder, iconsListDecoder } from '../api/decoders';
import { getBlock } from '../grid';
import IconPreview, { renderIconOption } from '../IconPreview';
import type {
  FieldBlock,
  Icon,
  ResourceOptions,
  SectionContext
} from '../models';
import { buildSelector } from '../selector';
import {
  labelActionUrl,
  labelAltIcon,
  labelComments,
  labelGeographicCoordinates,
  labelIcon,
  labelInvalidGeographicCoordinates,
  labelMustBeAtMostCharacters,
  labelNote,
  labelNoteUrl
} from '../translatedLabels';

// The three blocks fill the form's `extendedInfos` and the API's
// `extended_informations`.
interface ExtendedInfosDetail<Values> {
  extendedInfos: Values;
}

// The detail endpoint leaves unset values out rather than sending null.
const optionalStringDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.string)
).map((value) => value ?? '');

// Left out as a whole when every field of it is unset.
const getExtendedInfosDecoder = <Values>({
  decoders,
  defaultValues,
  keyMap
}: {
  decoders: JsonDecoder.DecoderObject<Values>;
  defaultValues: Values;
  keyMap: JsonDecoder.DecoderObjectKeyMap<Values>;
}): JsonDecoder.DecoderObject<ExtendedInfosDetail<Values>> =>
  ({
    extendedInfos: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<Values>(decoders, 'Extended informations', keyMap)
      )
    ).map((value) => value ?? defaultValues)
  }) as JsonDecoder.DecoderObject<ExtendedInfosDetail<Values>>;

const detailKeyMap = { extendedInfos: 'extended_informations' } as const;

const getExtendedInfos = <Values>(
  values: Record<string, unknown>,
  defaultValues: Values
): Values => (values.extendedInfos ?? defaultValues) as Values;

// The API refuses a blank string: none is null.
const toApiText = (value: string | undefined): string | null =>
  value?.trim() || null;

const getTextInput = ({
  fieldName,
  idPrefix,
  label,
  multilineRows
}: {
  fieldName: string;
  idPrefix: string;
  label: string;
  multilineRows?: number;
}): InputPropsWithoutGroup => ({
  dataTestId: `${idPrefix}-extended-infos-${fieldName}`,
  fieldName: `extendedInfos.${fieldName}`,
  label,
  ...(multilineRows && { text: { multilineRows } }),
  type: InputType.Text
});

// The lengths `ExtendedInformations` refuses beyond, checked as the server
// trims.
const getMaxLengthSchema = ({
  label,
  max,
  t
}: Pick<SectionContext, 't'> & { label: string; max: number }) =>
  string()
    .trim()
    .max(max, t(labelMustBeAtMostCharacters, { label: t(label), max }));

interface NotesAndIconsValues {
  altIcon: string;
  icon: Icon | null;
  note: string;
  noteUrl: string;
}

const defaultNotesAndIcons: NotesAndIconsValues = {
  altIcon: '',
  icon: null,
  note: '',
  noteUrl: ''
};

// A note, its link, and the icon the resource is shown with. The alternative
// text of the icon is refused on cloud.
export const notesAndIcons = ({
  mediasEndpoint,
  resource: { baseEndpoint, idPrefix }
}: {
  mediasEndpoint: string;
  resource: ResourceOptions;
}): FieldBlock<ExtendedInfosDetail<NotesAndIconsValues>> => ({
  defaultValues: { extendedInfos: defaultNotesAndIcons },
  detailDecoders: getExtendedInfosDecoder<NotesAndIconsValues>({
    decoders: {
      altIcon: optionalStringDecoder,
      icon: JsonDecoder.optional(JsonDecoder.nullable(iconDecoder)).map(
        (value) => value ?? null
      ),
      note: optionalStringDecoder,
      noteUrl: optionalStringDecoder
    },
    defaultValues: defaultNotesAndIcons,
    keyMap: {
      altIcon: 'alt_icon',
      icon: 'icon',
      note: 'note',
      noteUrl: 'note_url'
    }
  }),
  detailKeyMap,
  getInputs: ({ isCloudPlatform, t }) => {
    const icon: InputPropsWithoutGroup = {
      fieldName: 'extended-infos-icon',
      grid: {
        alignItems: 'center',
        className: 'grid-cols-[minmax(0,1fr)_auto]',
        columns: [
          {
            connectedAutocomplete: buildSelector({
              baseEndpoint,
              decoder: iconsListDecoder,
              endpoint: mediasEndpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              getRenderedOptionText: renderIconOption,
              queryKey: `${idPrefix}-icon`
            }),
            dataTestId: `${idPrefix}-extended-infos-icon`,
            fieldName: 'extendedInfos.icon',
            label: t(labelIcon),
            type: InputType.SingleConnectedAutocomplete
          },
          {
            // Labelled for the grid's key only: it renders no label.
            custom: { Component: IconPreview },
            dataTestId: `${idPrefix}-extended-infos-icon-preview`,
            fieldName: 'extended-infos-icon-preview',
            label: `${idPrefix}-extended-infos-icon-preview`,
            type: InputType.Custom
          }
        ]
      },
      label: `${idPrefix}-extended-infos-icon`,
      type: InputType.Grid
    };

    return {
      notesAndIcons: getBlock({
        columns: [
          getTextInput({ fieldName: 'note', idPrefix, label: t(labelNote) }),
          getTextInput({
            fieldName: 'noteUrl',
            idPrefix,
            label: t(labelNoteUrl)
          }),
          icon,
          ...(isCloudPlatform
            ? []
            : [
                getTextInput({
                  fieldName: 'altIcon',
                  idPrefix,
                  label: t(labelAltIcon)
                })
              ])
        ],
        idPrefix,
        name: 'extended-infos-details-left'
      })
    };
  },
  getSchema: ({ t }) => ({
    extendedInfos: object({
      altIcon: getMaxLengthSchema({ label: labelAltIcon, max: 200, t }),
      note: getMaxLengthSchema({ label: labelNote, max: 512, t }),
      noteUrl: getMaxLengthSchema({ label: labelNoteUrl, max: 2048, t })
    })
  }),
  toPayload: (values, { isCloudPlatform }) => {
    const infos = getExtendedInfos(values, defaultNotesAndIcons);

    return {
      extended_informations: {
        icon_id: infos.icon?.id ?? null,
        note: toApiText(infos.note),
        note_url: toApiText(infos.noteUrl),
        // Refused on cloud, where the server keeps it unset.
        ...(!isCloudPlatform && { alt_icon: toApiText(infos.altIcon) })
      }
    };
  }
});

// `GeoCoordinates`, pattern for pattern: the server truncates each part to six
// decimals before matching it, so more decimals are not an error.
const latitude = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)$/;
const longitude = /^[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
const maxDecimals = 6;

const truncateDecimals = (value: string): string => {
  const dotPosition = value.indexOf('.');

  return dotPosition === -1
    ? value
    : value.slice(0, dotPosition + 1 + maxDecimals);
};

export const isValidGeoCoordinates = (value: string): boolean => {
  const parts = value.trim().split(',');

  if (parts.length !== 2) {
    return false;
  }

  const [lat, lon] = parts.map((part) => truncateDecimals(part.trim()));

  return latitude.test(lat) && longitude.test(lon);
};

interface ActionUrlAndGeoCoordinatesValues {
  actionUrl: string;
  geoCoordinates: string;
}

const defaultActionUrlAndGeoCoordinates: ActionUrlAndGeoCoordinatesValues = {
  actionUrl: '',
  geoCoordinates: ''
};

// A link to act on the resource, and where it stands on a map.
export const actionUrlAndGeoCoordinates = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<ExtendedInfosDetail<ActionUrlAndGeoCoordinatesValues>> => ({
  defaultValues: { extendedInfos: defaultActionUrlAndGeoCoordinates },
  detailDecoders: getExtendedInfosDecoder<ActionUrlAndGeoCoordinatesValues>({
    decoders: {
      actionUrl: optionalStringDecoder,
      geoCoordinates: optionalStringDecoder
    },
    defaultValues: defaultActionUrlAndGeoCoordinates,
    keyMap: { actionUrl: 'action_url', geoCoordinates: 'geo_coordinates' }
  }),
  detailKeyMap,
  getInputs: ({ t }) => ({
    actionUrlAndGeoCoordinates: getBlock({
      columns: [
        getTextInput({
          fieldName: 'actionUrl',
          idPrefix,
          label: t(labelActionUrl)
        }),
        getTextInput({
          fieldName: 'geoCoordinates',
          idPrefix,
          label: t(labelGeographicCoordinates)
        })
      ],
      idPrefix,
      name: 'extended-infos-details-right'
    })
  }),
  getSchema: ({ t }) => ({
    extendedInfos: object({
      actionUrl: getMaxLengthSchema({ label: labelActionUrl, max: 2048, t }),
      // No length of its own: its format bounds it.
      geoCoordinates: string().test(
        'is-geo-coordinates',
        t(labelInvalidGeographicCoordinates),
        (value) => !value?.trim() || isValidGeoCoordinates(value)
      )
    })
  }),
  toPayload: (values) => {
    const infos = getExtendedInfos(values, defaultActionUrlAndGeoCoordinates);

    return {
      extended_informations: {
        action_url: toApiText(infos.actionUrl),
        geo_coordinates: toApiText(infos.geoCoordinates)
      }
    };
  }
});

const defaultComment = { comment: '' };

// A free comment on the resource. Refused on cloud.
export const comment = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<ExtendedInfosDetail<{ comment: string }>> => ({
  defaultValues: { extendedInfos: defaultComment },
  detailDecoders: getExtendedInfosDecoder<{ comment: string }>({
    decoders: { comment: optionalStringDecoder },
    defaultValues: defaultComment,
    keyMap: { comment: 'comment' }
  }),
  detailKeyMap,
  getInputs: ({ isCloudPlatform, t }) =>
    isCloudPlatform
      ? {}
      : {
          comment: {
            // Titled as designed, above the field's own label.
            additionalLabel: t(labelComments),
            ...getTextInput({
              fieldName: 'comment',
              idPrefix,
              label: t(labelComments),
              multilineRows: 3
            })
          }
        },
  getSchema: ({ t }) => ({
    extendedInfos: object({
      comment: getMaxLengthSchema({ label: labelComments, max: 65535, t })
    })
  }),
  toPayload: (values, { isCloudPlatform }) =>
    isCloudPlatform
      ? {}
      : {
          extended_informations: {
            comment: toApiText(getExtendedInfos(values, defaultComment).comment)
          }
        }
});
