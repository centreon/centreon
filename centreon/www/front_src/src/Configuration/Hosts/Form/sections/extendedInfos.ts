import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { object, string } from 'yup';

import {
  hostFormHostSeveritiesEndpoint,
  hostFormMediasEndpoint
} from '../../api/endpoints';
import {
  iconDecoder,
  iconsListDecoder,
  namedEntityDecoder
} from '../../api/namedEntityDecoders';
import type { Icon, NamedEntity } from '../../models';
import {
  labelActionUrl,
  labelAltIcon,
  labelComments,
  labelGeographicCoordinates,
  labelHostExtendedInfos,
  labelHostSeverity,
  labelIcon,
  labelInvalidGeographicCoordinates,
  labelMustBeAtMostCharacters,
  labelNote,
  labelNoteUrl
} from '../../translatedLabels';
import IconPreview, { renderIconOption } from '../IconPreview';
import { buildSelector } from '../selector';
import type { FormSection, SectionContext } from './models';

interface ExtendedInfosValues {
  actionUrl: string;
  altIcon: string;
  comment: string;
  geoCoordinates: string;
  icon: Icon | null;
  note: string;
  noteUrl: string;
}

type TextField = Exclude<keyof ExtendedInfosValues, 'icon'>;

interface ExtendedInfosDetail {
  extendedInfos: ExtendedInfosValues;
  severity: NamedEntity | null;
}

const defaultExtendedInfos: ExtendedInfosValues = {
  actionUrl: '',
  altIcon: '',
  comment: '',
  geoCoordinates: '',
  icon: null,
  note: '',
  noteUrl: ''
};

// The lengths `ExtendedInformations` refuses beyond. The geographic
// coordinates have none: their format bounds them.
const maxLengths: Record<Exclude<TextField, 'geoCoordinates'>, number> = {
  actionUrl: 2048,
  altIcon: 200,
  comment: 65535,
  note: 512,
  noteUrl: 2048
};

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

// The detail endpoint leaves unset values out rather than sending null.
const optionalStringDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.string)
).map((value) => value ?? '');

const extendedInfosDecoder = JsonDecoder.object<ExtendedInfosValues>(
  {
    actionUrl: optionalStringDecoder,
    altIcon: optionalStringDecoder,
    comment: optionalStringDecoder,
    geoCoordinates: optionalStringDecoder,
    icon: JsonDecoder.optional(JsonDecoder.nullable(iconDecoder)).map(
      (value) => value ?? null
    ),
    note: optionalStringDecoder,
    noteUrl: optionalStringDecoder
  },
  'Extended informations',
  {
    actionUrl: 'action_url',
    altIcon: 'alt_icon',
    comment: 'comment',
    geoCoordinates: 'geo_coordinates',
    icon: 'icon',
    note: 'note',
    noteUrl: 'note_url'
  }
);

// The API refuses a blank string: none is null.
const toApiText = (value: string | undefined): string | null =>
  value?.trim() || null;

const getTextInput = ({
  fieldName,
  label,
  multilineRows
}: {
  fieldName: TextField;
  label: string;
  multilineRows?: number;
}): InputPropsWithoutGroup => ({
  dataTestId: `host-form-extended-infos-${fieldName}`,
  fieldName: `extendedInfos.${fieldName}`,
  label,
  ...(multilineRows && { text: { multilineRows } }),
  type: InputType.Text
});

const getMaxLengthSchema = ({
  field,
  label,
  t
}: Pick<SectionContext, 't'> & {
  field: keyof typeof maxLengths;
  label: string;
}) =>
  string()
    .trim()
    .max(
      maxLengths[field],
      t(labelMustBeAtMostCharacters, {
        label: t(label),
        max: maxLengths[field]
      })
    );

export const extendedInfos: FormSection<ExtendedInfosDetail> = {
  defaultValues: { extendedInfos: defaultExtendedInfos, severity: null },
  detailDecoders: {
    // Left out as a whole when every field of it is unset.
    extendedInfos: JsonDecoder.optional(
      JsonDecoder.nullable(extendedInfosDecoder)
    ).map((value) => value ?? defaultExtendedInfos),
    // Left out too when the user's ACLs do not cover it.
    severity: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(namedEntityDecoder, 'Severity'))
    ).map((value) => value ?? null)
  },
  detailKeyMap: { extendedInfos: 'extended_informations' },
  getInputs: ({ isCloudPlatform, t }) => {
    const icon: InputPropsWithoutGroup = {
      fieldName: 'extended-infos-icon',
      grid: {
        alignItems: 'center',
        className: 'grid-cols-[minmax(0,1fr)_auto]',
        columns: [
          {
            connectedAutocomplete: buildSelector({
              decoder: iconsListDecoder,
              endpoint: hostFormMediasEndpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              getRenderedOptionText: renderIconOption,
              queryKey: 'host-form-icon'
            }),
            dataTestId: 'host-form-extended-infos-icon',
            fieldName: 'extendedInfos.icon',
            label: t(labelIcon),
            type: InputType.SingleConnectedAutocomplete
          },
          {
            // Labelled for the grid's key only: it renders no label.
            custom: { Component: IconPreview },
            dataTestId: 'host-form-extended-infos-icon-preview',
            fieldName: 'extended-infos-icon-preview',
            label: 'host-form-extended-infos-icon-preview',
            type: InputType.Custom
          }
        ]
      },
      label: 'host-form-extended-infos-icon',
      type: InputType.Grid
    };

    const severity: InputPropsWithoutGroup = {
      // The host-scoped selector gives no level to show beside the name.
      connectedAutocomplete: buildSelector({
        endpoint: hostFormHostSeveritiesEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: 'host-form-severity'
      }),
      dataTestId: 'host-form-severity',
      fieldName: 'severity',
      label: t(labelHostSeverity),
      type: InputType.SingleConnectedAutocomplete
    };

    return [
      {
        fieldName: 'extended-infos-details',
        grid: {
          className: 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2',
          columns: [
            getTextInput({ fieldName: 'note', label: t(labelNote) }),
            getTextInput({ fieldName: 'actionUrl', label: t(labelActionUrl) }),
            getTextInput({ fieldName: 'noteUrl', label: t(labelNoteUrl) }),
            getTextInput({
              fieldName: 'geoCoordinates',
              label: t(labelGeographicCoordinates)
            }),
            icon,
            ...(isCloudPlatform
              ? []
              : [
                  getTextInput({ fieldName: 'altIcon', label: t(labelAltIcon) })
                ]),
            severity
          ]
        },
        label: 'host-form-extended-infos-details',
        type: InputType.Grid
      },
      ...(isCloudPlatform
        ? []
        : [
            getTextInput({
              fieldName: 'comment',
              label: t(labelComments),
              multilineRows: 3
            })
          ])
    ];
  },
  getSchema: ({ t }) => ({
    extendedInfos: object({
      actionUrl: getMaxLengthSchema({
        field: 'actionUrl',
        label: labelActionUrl,
        t
      }),
      altIcon: getMaxLengthSchema({ field: 'altIcon', label: labelAltIcon, t }),
      comment: getMaxLengthSchema({
        field: 'comment',
        label: labelComments,
        t
      }),
      geoCoordinates: string().test(
        'is-geo-coordinates',
        t(labelInvalidGeographicCoordinates),
        (value) => !value?.trim() || isValidGeoCoordinates(value)
      ),
      note: getMaxLengthSchema({ field: 'note', label: labelNote, t }),
      noteUrl: getMaxLengthSchema({ field: 'noteUrl', label: labelNoteUrl, t })
    })
  }),
  label: labelHostExtendedInfos,
  toPayload: (values, { isCloudPlatform }) => {
    const { extendedInfos: infos = defaultExtendedInfos, severity } =
      values as {
        extendedInfos?: ExtendedInfosValues;
        severity?: NamedEntity | null;
      };

    return {
      extended_informations: {
        action_url: toApiText(infos.actionUrl),
        geo_coordinates: toApiText(infos.geoCoordinates),
        icon_id: infos.icon?.id ?? null,
        note: toApiText(infos.note),
        note_url: toApiText(infos.noteUrl),
        // Refused on cloud, where the server keeps them unset.
        ...(!isCloudPlatform && {
          alt_icon: toApiText(infos.altIcon),
          comment: toApiText(infos.comment)
        })
      },
      // A field of the host itself, not of its extended informations.
      severity_id: severity?.id ?? null
    };
  }
};
