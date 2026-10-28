import {
  buildListingDecoder,
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { array, object, string } from 'yup';

// Pure helpers and components of the Hosts form, imported rather than copied:
// the service icon and coordinates follow the very same rules.
import {
  iconDecoder,
  iconsListDecoder,
  namedEntityDecoder
} from '../../../Hosts/api/namedEntityDecoders';
import IconPreview, { renderIconOption } from '../../../Hosts/Form/IconPreview';
import { isValidGeoCoordinates } from '../../../Hosts/Form/sections/extendedInfos';
import { buildSelector, toIds } from '../../../Hosts/Form/selector';
import type { Icon, NamedEntity } from '../../models';
import {
  labelServiceActionUrl,
  labelServiceAltIcon,
  labelServiceCategories,
  labelServiceComments,
  labelServiceExtendedInfo,
  labelServiceGeographicCoordinates,
  labelServiceGraphTemplate,
  labelServiceIcon,
  labelServiceInvalidGeographicCoordinates,
  labelServiceMustBeAtMostCharacters,
  labelServiceNote,
  labelServiceNoteUrl,
  labelServiceSeverity,
  labelServiceSeverityWithLevel
} from '../../translatedLabels';
import { withUpdateMode } from '../updateMode';
import type { FormSection, SectionContext } from './models';

// Placeholders, on the model of the `/configuration/hosts/*` selectors: the
// service-scoped routes do not exist yet.
export const serviceFormGraphTemplatesEndpoint =
  '/configuration/services/graph_templates';
export const serviceFormServiceCategoriesEndpoint =
  '/configuration/services/service_categories';
export const serviceFormServiceSeveritiesEndpoint =
  '/configuration/services/service_severities';
export const serviceFormMediasEndpoint = '/configuration/services/medias';

type Severity = NamedEntity & { level?: number | null };

// The keys and shape of the Hosts form, so its icon preview reads them as is.
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

interface ExtendedInfoDetail {
  categories: Array<NamedEntity>;
  extendedInfos: ExtendedInfosValues;
  graphTemplate: NamedEntity | null;
  severity: Severity | null;
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

// The lengths the API refuses beyond (F44 to F50). The geographic coordinates
// have none: their format bounds them.
const maxLengths: Record<Exclude<TextField, 'geoCoordinates'>, number> = {
  actionUrl: 2048,
  altIcon: 200,
  comment: 65535,
  note: 512,
  noteUrl: 2048
};

const textLabels: Record<TextField, string> = {
  actionUrl: labelServiceActionUrl,
  altIcon: labelServiceAltIcon,
  comment: labelServiceComments,
  geoCoordinates: labelServiceGeographicCoordinates,
  note: labelServiceNote,
  noteUrl: labelServiceNoteUrl
};

// Pendo tags: fixed, never derived from a label.
const textTestIds: Record<TextField, string> = {
  actionUrl: 'service-form-action-url',
  altIcon: 'service-form-alt-icon',
  comment: 'service-form-comments',
  geoCoordinates: 'service-form-geo-coordinates',
  note: 'service-form-note',
  noteUrl: 'service-form-note-url'
};

// The detail endpoint leaves unset values out rather than sending null.
const optionalStringDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.string)
).map((value) => value ?? '');

const optionalLevelDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
);

const severityDecoder = JsonDecoder.object<Severity>(
  { ...namedEntityDecoder, level: optionalLevelDecoder },
  'Severity'
);

const severitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: severityDecoder,
  entityDecoderName: 'Severity',
  listingDecoderName: 'Severity List'
});

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

// A severity reads `name (level)`, as in the legacy select.
const getSeverityLabel =
  (t: TFunction) =>
  (option: unknown): string => {
    const severity = option as Severity | null;

    if (!severity) {
      return '';
    }

    return severity.level === undefined || severity.level === null
      ? severity.name
      : t(labelServiceSeverityWithLevel, {
          level: severity.level,
          name: severity.name
        });
  };

const getTextInput = ({
  fieldName,
  multilineRows,
  t
}: Pick<SectionContext, 't'> & {
  fieldName: TextField;
  multilineRows?: number;
}): InputPropsWithoutGroup => ({
  dataTestId: textTestIds[fieldName],
  fieldName: `extendedInfos.${fieldName}`,
  label: t(textLabels[fieldName]),
  ...(multilineRows && { text: { multilineRows } }),
  type: InputType.Text
});

const getMaxLengthSchema = ({
  field,
  t
}: Pick<SectionContext, 't'> & { field: keyof typeof maxLengths }) =>
  string()
    .trim()
    .max(
      maxLengths[field],
      t(labelServiceMustBeAtMostCharacters, {
        label: t(textLabels[field]),
        max: maxLengths[field]
      })
    );

const getIconInput = ({ t }: Pick<SectionContext, 't'>) => ({
  fieldName: 'extended-info-icon',
  grid: {
    alignItems: 'center',
    className: 'grid-cols-[minmax(0,1fr)_auto]',
    columns: [
      {
        connectedAutocomplete: buildSelector({
          decoder: iconsListDecoder,
          endpoint: serviceFormMediasEndpoint,
          getOptionLabel: (option) => (option as SelectEntry)?.name,
          getRenderedOptionText: renderIconOption,
          queryKey: 'service-form-icon'
        }),
        dataTestId: 'service-form-icon',
        fieldName: 'extendedInfos.icon',
        label: t(labelServiceIcon),
        type: InputType.SingleConnectedAutocomplete
      },
      {
        // Labelled for the grid's key only: it renders no label.
        custom: { Component: IconPreview },
        dataTestId: 'service-form-icon-preview',
        fieldName: 'extended-info-icon-preview',
        label: 'service-form-icon-preview',
        type: InputType.Custom
      }
    ]
  },
  label: 'service-form-icon',
  type: InputType.Grid
});

export const extendedInfo: FormSection<ExtendedInfoDetail> = {
  defaultValues: {
    categories: [],
    extendedInfos: defaultExtendedInfos,
    graphTemplate: null,
    severity: null
  },
  detailDecoders: {
    // Only the categories the user may see: the server keeps the others.
    categories: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object(namedEntityDecoder, 'Category'),
        'Categories'
      )
    ).map((value) => value ?? []),
    // Left out as a whole when every field of it is unset.
    extendedInfos: JsonDecoder.optional(
      JsonDecoder.nullable(extendedInfosDecoder)
    ).map((value) => value ?? defaultExtendedInfos),
    // On-premise only: never in a cloud response.
    graphTemplate: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object(namedEntityDecoder, 'Graph template')
      )
    ).map((value) => value ?? null),
    // Left out too when the user's ACLs do not cover it; the server then
    // keeps it on update, as for hosts.
    severity: JsonDecoder.optional(JsonDecoder.nullable(severityDecoder)).map(
      (value) => value ?? null
    )
  },
  detailKeyMap: {
    extendedInfos: 'extended_informations',
    graphTemplate: 'graph_template'
  },
  getInputs: ({ isCloudPlatform, isMassChange, t }) => {
    const categories: InputPropsWithoutGroup = {
      connectedAutocomplete: buildSelector({
        chipColor: 'primary',
        endpoint: serviceFormServiceCategoriesEndpoint,
        queryKey: 'service-form-categories'
      }),
      dataTestId: 'service-form-categories',
      fieldName: 'categories',
      label: t(labelServiceCategories),
      type: InputType.MultiConnectedAutocomplete
    };

    const severity: InputPropsWithoutGroup = {
      connectedAutocomplete: buildSelector({
        decoder: severitiesListDecoder,
        endpoint: serviceFormServiceSeveritiesEndpoint,
        // The options and the picked value alike.
        getOptionLabel: getSeverityLabel(t),
        getRenderedOptionText: getSeverityLabel(t),
        queryKey: 'service-form-severity'
      }),
      dataTestId: 'service-form-severity',
      fieldName: 'severity',
      label: t(labelServiceSeverity),
      type: InputType.SingleConnectedAutocomplete
    };

    const graphTemplate: InputPropsWithoutGroup = {
      connectedAutocomplete: buildSelector({
        endpoint: serviceFormGraphTemplatesEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: 'service-form-graph-template'
      }),
      dataTestId: 'service-form-graph-template',
      fieldName: 'graphTemplate',
      label: t(labelServiceGraphTemplate),
      type: InputType.SingleConnectedAutocomplete
    };

    return [
      {
        fieldName: 'extended-info-details',
        grid: {
          className: 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2',
          columns: [
            ...(isCloudPlatform ? [] : [graphTemplate]),
            severity,
            getTextInput({ fieldName: 'note', t }),
            getTextInput({ fieldName: 'noteUrl', t }),
            getTextInput({ fieldName: 'actionUrl', t }),
            getTextInput({ fieldName: 'geoCoordinates', t }),
            getIconInput({ t }),
            ...(isCloudPlatform
              ? []
              : [getTextInput({ fieldName: 'altIcon', t })])
          ]
        },
        label: 'service-form-extended-info-details',
        type: InputType.Grid
      },
      withUpdateMode({
        field: 'categories',
        input: categories,
        isMassChange,
        t
      }),
      ...(isCloudPlatform
        ? []
        : [getTextInput({ fieldName: 'comment', multilineRows: 3, t })])
    ];
  },
  getSchema: ({ t }) => ({
    categories: array().notRequired(),
    extendedInfos: object({
      actionUrl: getMaxLengthSchema({ field: 'actionUrl', t }),
      altIcon: getMaxLengthSchema({ field: 'altIcon', t }),
      comment: getMaxLengthSchema({ field: 'comment', t }),
      geoCoordinates: string().test(
        'is-geo-coordinates',
        t(labelServiceInvalidGeographicCoordinates),
        (value) => !value?.trim() || isValidGeoCoordinates(value)
      ),
      note: getMaxLengthSchema({ field: 'note', t }),
      noteUrl: getMaxLengthSchema({ field: 'noteUrl', t })
    })
  }),
  label: labelServiceExtendedInfo,
  toPayload: (values, { isCloudPlatform }) => {
    const {
      categories,
      extendedInfos: infos = defaultExtendedInfos,
      graphTemplate,
      severity
    } = values as {
      categories?: Array<{ id: number }> | null;
      extendedInfos?: ExtendedInfosValues;
      graphTemplate?: NamedEntity | null;
      severity?: Severity | null;
    };

    return {
      // The visible ones only: the server adds back those the user cannot see.
      category_ids: toIds(categories),
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
      ...(!isCloudPlatform && {
        graph_template_id: graphTemplate?.id ?? null
      }),
      severity_id: severity?.id ?? null
    };
  }
};
