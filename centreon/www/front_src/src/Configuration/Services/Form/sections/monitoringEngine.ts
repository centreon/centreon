import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

// Pure helpers and components of the Hosts form, imported rather than copied:
// the service icon and coordinates follow the very same rules.
import {
  iconDecoder,
  iconsListDecoder
} from '../../../Hosts/api/namedEntityDecoders';
import IconPreview, { renderIconOption } from '../../../Hosts/Form/IconPreview';
import { isValidGeoCoordinates } from '../../../Hosts/Form/sections/extendedInfos';
import { buildSelector as buildHostsSelector } from '../../../Hosts/Form/selector';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../../../Hosts/Form/triState';
import type { Icon, NamedEntity } from '../../models';
import {
  labelActiveChecksEnabled,
  labelCheckPeriod,
  labelIsVolatile,
  labelMaxCheckAttempts,
  labelMonitoringEngine,
  labelMonitoringEngineSchedulingOptions,
  labelMustBeIntegerOfAtLeastOne,
  labelNormalCheckInterval,
  labelPassiveChecksEnabled,
  labelRetryCheckInterval,
  labelSchedulingOptions,
  labelServiceActionUrl,
  labelServiceAltIcon,
  labelServiceGeographicCoordinates,
  labelServiceIcon,
  labelServiceInvalidGeographicCoordinates,
  labelServiceMustBeAtMostCharacters,
  labelServiceNote,
  labelServiceNoteUrl
} from '../../translatedLabels';
import type { FormSection, SectionContext } from './models';
import { buildSelector, optionalNamedEntityDecoder } from './selector';

// Placeholders, on the model of the `/configuration/hosts/*` selectors: the
// service-scoped routes do not exist yet.
export const serviceFormMediasEndpoint = '/configuration/services/medias';
export const serviceFormTimePeriodsEndpoint =
  '/configuration/services/timeperiods';

type OptionalNumber = number | '';

interface SchedulingOptionsValues {
  activeCheckEnabled: TriState;
  checkPeriod: NamedEntity | null;
  isVolatile: TriState;
  maxCheckAttempts: OptionalNumber;
  normalCheckInterval: OptionalNumber;
  passiveCheckEnabled: TriState;
  retryCheckInterval: OptionalNumber;
}

// The keys and shape of the Hosts form, so its icon preview reads them as is.
// The comment, sent in the same API object, belongs to Advanced.
interface ExtendedInfosValues {
  actionUrl: string;
  altIcon: string;
  geoCoordinates: string;
  icon: Icon | null;
  note: string;
  noteUrl: string;
}

type TextField = Exclude<keyof ExtendedInfosValues, 'icon'>;

interface MonitoringEngineDetail {
  extendedInfos: ExtendedInfosValues;
  schedulingOptions: SchedulingOptionsValues;
}

const defaultSchedulingOptions: SchedulingOptionsValues = {
  activeCheckEnabled: defaultTriState,
  checkPeriod: null,
  isVolatile: defaultTriState,
  maxCheckAttempts: '',
  normalCheckInterval: '',
  passiveCheckEnabled: defaultTriState,
  retryCheckInterval: ''
};

const defaultExtendedInfos: ExtendedInfosValues = {
  actionUrl: '',
  altIcon: '',
  geoCoordinates: '',
  icon: null,
  note: '',
  noteUrl: ''
};

// The lengths the API refuses beyond (F44 to F49). The geographic coordinates
// have none: their format bounds them.
const maxLengths: Record<Exclude<TextField, 'geoCoordinates'>, number> = {
  actionUrl: 2048,
  altIcon: 200,
  note: 512,
  noteUrl: 2048
};

const textLabels: Record<TextField, string> = {
  actionUrl: labelServiceActionUrl,
  altIcon: labelServiceAltIcon,
  geoCoordinates: labelServiceGeographicCoordinates,
  note: labelServiceNote,
  noteUrl: labelServiceNoteUrl
};

// Pendo tags: fixed, never derived from a label.
const textTestIds: Record<TextField, string> = {
  actionUrl: 'service-form-action-url',
  altIcon: 'service-form-alt-icon',
  geoCoordinates: 'service-form-geo-coordinates',
  note: 'service-form-note',
  noteUrl: 'service-form-note-url'
};

const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

// Absent means Default, as the API reads it.
const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);

// The detail endpoint leaves unset values out rather than sending null.
const optionalStringDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.string)
).map((value) => value ?? '');

const schedulingOptionsDecoder = JsonDecoder.object<SchedulingOptionsValues>(
  {
    activeCheckEnabled: optionalTriStateDecoder,
    checkPeriod: optionalNamedEntityDecoder('Check period'),
    isVolatile: optionalTriStateDecoder,
    maxCheckAttempts: optionalNumberDecoder,
    normalCheckInterval: optionalNumberDecoder,
    passiveCheckEnabled: optionalTriStateDecoder,
    retryCheckInterval: optionalNumberDecoder
  },
  'Scheduling options',
  {
    activeCheckEnabled: 'active_check_enabled',
    checkPeriod: 'check_period',
    isVolatile: 'is_volatile',
    maxCheckAttempts: 'max_check_attempts',
    normalCheckInterval: 'normal_check_interval',
    passiveCheckEnabled: 'passive_check_enabled',
    retryCheckInterval: 'retry_check_interval'
  }
);

const extendedInfosDecoder = JsonDecoder.object<ExtendedInfosValues>(
  {
    actionUrl: optionalStringDecoder,
    altIcon: optionalStringDecoder,
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
    geoCoordinates: 'geo_coordinates',
    icon: 'icon',
    note: 'note',
    noteUrl: 'note_url'
  }
);

// Empty leaves the value to the template chain.
const toApiNumber = (value: OptionalNumber | undefined): number | null =>
  value === '' || value === undefined ? null : Number(value);

// The API refuses a blank string: none is null.
const toApiText = (value: string | undefined): string | null =>
  value?.trim() || null;

const getAtLeastOneSchema = (message: string) =>
  number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(1, message);

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

const getTextInput = ({
  fieldName,
  t
}: Pick<SectionContext, 't'> & {
  fieldName: TextField;
}): InputPropsWithoutGroup => ({
  dataTestId: textTestIds[fieldName],
  fieldName: `extendedInfos.${fieldName}`,
  label: t(textLabels[fieldName]),
  type: InputType.Text
});

const getIconInput = ({ t }: Pick<SectionContext, 't'>) => ({
  fieldName: 'extended-info-icon',
  grid: {
    alignItems: 'center',
    className: 'grid-cols-[minmax(0,1fr)_auto]',
    columns: [
      {
        connectedAutocomplete: buildHostsSelector({
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

// A titled block of two columns, as the Figma panel draws them.
const getBlock = ({
  columns,
  name,
  title
}: {
  columns: [Array<InputPropsWithoutGroup>, Array<InputPropsWithoutGroup>];
  name: string;
  title: string;
}): InputPropsWithoutGroup => ({
  additionalLabel: title,
  fieldName: `service-${name}`,
  grid: {
    className: 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2',
    // A column left empty on cloud keeps its place, as in the mockup.
    columns: columns.map((inputs, index) => ({
      fieldName: `service-${name}-column-${index + 1}`,
      grid: { className: 'grid-cols-1', columns: inputs },
      label: `service-form-${name}-column-${index + 1}`,
      type: InputType.Grid
    }))
  },
  label: `service-form-${name}`,
  type: InputType.Grid
});

// F41 to F49 but the comment: Note, Note URL, Icon, Alt icon | Action URL,
// Geographic coordinates.
const getMonitoringEngineBlock = ({
  isCloudPlatform,
  t
}: Pick<SectionContext, 'isCloudPlatform' | 't'>): InputPropsWithoutGroup =>
  getBlock({
    columns: [
      [
        getTextInput({ fieldName: 'note', t }),
        getTextInput({ fieldName: 'noteUrl', t }),
        getIconInput({ t }),
        ...(isCloudPlatform ? [] : [getTextInput({ fieldName: 'altIcon', t })])
      ],
      [
        getTextInput({ fieldName: 'actionUrl', t }),
        getTextInput({ fieldName: 'geoCoordinates', t })
      ]
    ],
    name: 'monitoring-engine',
    title: t(labelMonitoringEngine)
  });

const getSchedulingNumberInput = ({
  dataTestId,
  fieldName,
  label
}: {
  dataTestId: string;
  fieldName: keyof SchedulingOptionsValues;
  label: string;
}): InputPropsWithoutGroup => ({
  dataTestId,
  fieldName: `schedulingOptions.${fieldName}`,
  label,
  text: { min: 1, type: 'number' },
  type: InputType.Text
});

const getSchedulingTriStateInput = ({
  dataTestId,
  fieldName,
  label
}: {
  dataTestId: string;
  fieldName: keyof SchedulingOptionsValues;
  label: string;
}): InputPropsWithoutGroup => ({
  dataTestId,
  fieldName: `schedulingOptions.${fieldName}`,
  label,
  segmentedButtons: { options: triStateOptions },
  type: InputType.SegmentedButtons
});

// F07 to F13: the check numbers | the Yes/No/Default toggles, which do not
// exist on cloud.
const getSchedulingBlock = ({
  isCloudPlatform,
  t
}: Pick<SectionContext, 'isCloudPlatform' | 't'>): InputPropsWithoutGroup =>
  getBlock({
    columns: [
      [
        {
          connectedAutocomplete: buildSelector({
            endpoint: serviceFormTimePeriodsEndpoint,
            queryKey: 'service-form-check-period'
          }),
          dataTestId: 'service-form-check-period',
          fieldName: 'schedulingOptions.checkPeriod',
          label: t(labelCheckPeriod),
          type: InputType.SingleConnectedAutocomplete
        },
        getSchedulingNumberInput({
          dataTestId: 'service-form-max-check-attempts',
          fieldName: 'maxCheckAttempts',
          label: t(labelMaxCheckAttempts)
        }),
        getSchedulingNumberInput({
          dataTestId: 'service-form-normal-check-interval',
          fieldName: 'normalCheckInterval',
          label: t(labelNormalCheckInterval)
        }),
        getSchedulingNumberInput({
          dataTestId: 'service-form-retry-check-interval',
          fieldName: 'retryCheckInterval',
          label: t(labelRetryCheckInterval)
        })
      ],
      isCloudPlatform
        ? []
        : [
            getSchedulingTriStateInput({
              dataTestId: 'service-form-active-checks-enabled',
              fieldName: 'activeCheckEnabled',
              label: t(labelActiveChecksEnabled)
            }),
            getSchedulingTriStateInput({
              dataTestId: 'service-form-passive-checks-enabled',
              fieldName: 'passiveCheckEnabled',
              label: t(labelPassiveChecksEnabled)
            }),
            getSchedulingTriStateInput({
              dataTestId: 'service-form-is-volatile',
              fieldName: 'isVolatile',
              label: t(labelIsVolatile)
            })
          ]
    ],
    name: 'scheduling-options',
    title: t(labelSchedulingOptions)
  });

// The Figma panel's fourth section: the legacy Extended Info's engine fields
// (MON-213087) above the scheduling options (MON-213082).
export const monitoringEngine: FormSection<MonitoringEngineDetail> = {
  defaultValues: {
    extendedInfos: defaultExtendedInfos,
    schedulingOptions: defaultSchedulingOptions
  },
  detailDecoders: {
    // Left out as a whole when every field of it is unset.
    extendedInfos: JsonDecoder.optional(
      JsonDecoder.nullable(extendedInfosDecoder)
    ).map((value) => value ?? defaultExtendedInfos),
    schedulingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(schedulingOptionsDecoder)
    ).map((value) => value ?? defaultSchedulingOptions)
  },
  detailKeyMap: {
    extendedInfos: 'extended_informations',
    schedulingOptions: 'scheduling_options'
  },
  getInputs: ({ isCloudPlatform, t }) => [
    getMonitoringEngineBlock({ isCloudPlatform, t }),
    getSchedulingBlock({ isCloudPlatform, t })
  ],
  getSchema: ({ t }) => ({
    extendedInfos: object({
      actionUrl: getMaxLengthSchema({ field: 'actionUrl', t }),
      altIcon: getMaxLengthSchema({ field: 'altIcon', t }),
      geoCoordinates: string().test(
        'is-geo-coordinates',
        t(labelServiceInvalidGeographicCoordinates),
        (value) => !value?.trim() || isValidGeoCoordinates(value)
      ),
      note: getMaxLengthSchema({ field: 'note', t }),
      noteUrl: getMaxLengthSchema({ field: 'noteUrl', t })
    }),
    schedulingOptions: object({
      maxCheckAttempts: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne)),
      normalCheckInterval: getAtLeastOneSchema(
        t(labelMustBeIntegerOfAtLeastOne)
      ),
      retryCheckInterval: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne))
    })
  }),
  label: labelMonitoringEngineSchedulingOptions,
  toPayload: (values, { isCloudPlatform }) => {
    const {
      extendedInfos: infos = defaultExtendedInfos,
      schedulingOptions = defaultSchedulingOptions
    } = values as {
      extendedInfos?: ExtendedInfosValues;
      schedulingOptions?: SchedulingOptionsValues;
    };

    return {
      extended_informations: {
        action_url: toApiText(infos.actionUrl),
        geo_coordinates: toApiText(infos.geoCoordinates),
        icon_id: infos.icon?.id ?? null,
        note: toApiText(infos.note),
        note_url: toApiText(infos.noteUrl),
        // Refused on cloud, where the server keeps it unset.
        ...(!isCloudPlatform && { alt_icon: toApiText(infos.altIcon) })
      },
      scheduling_options: {
        check_timeperiod_id: schedulingOptions.checkPeriod?.id ?? null,
        max_check_attempts: toApiNumber(schedulingOptions.maxCheckAttempts),
        normal_check_interval: toApiNumber(
          schedulingOptions.normalCheckInterval
        ),
        retry_check_interval: toApiNumber(schedulingOptions.retryCheckInterval),
        // Refused on cloud rather than ignored.
        ...(!isCloudPlatform && {
          active_check_enabled: schedulingOptions.activeCheckEnabled,
          is_volatile: schedulingOptions.isVolatile,
          passive_check_enabled: schedulingOptions.passiveCheckEnabled
        })
      }
    };
  }
};
