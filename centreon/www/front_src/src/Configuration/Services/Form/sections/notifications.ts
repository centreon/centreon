import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { FormikValues } from 'formik';
import { path } from 'ramda';
import { JsonDecoder } from 'ts.data.json';
import { mixed, number, object } from 'yup';

// Pure helpers of the Hosts form, imported rather than copied: the selectors
// answer in the same Hydra shape and the Yes / No / Default values are the
// same API enum.
import { namedEntityDecoder } from '../../../Hosts/api/namedEntityDecoders';
import { buildSelector, toIds } from '../../../Hosts/Form/selector';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../../../Hosts/Form/triState';
import type { NamedEntity } from '../../../Hosts/models';
import { servicesBaseEndpoint } from '../../api/endpoints';
import {
  labelContactAdditiveInheritance,
  labelContactGroupAdditiveInheritance,
  labelCritical,
  labelDowntimeScheduled,
  labelFirstNotificationDelay,
  labelFlapping,
  labelInheritContactsFromHost,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelMustBePositiveIntegerOrZero,
  labelNo,
  labelNone,
  labelNotificationEnabled,
  labelNotificationInterval,
  labelNotificationOptions,
  labelNotificationPeriod,
  labelNotifications,
  labelRecovery,
  labelRecoveryNotificationDelay,
  labelUnknown,
  labelWarning,
  labelYes
} from '../../translatedLabels';
import { getUpdateModeInput, withUpdateMode } from '../updateMode';
import type { FormSection } from './models';

// Placeholders until the backend exposes the service form selectors
// (MON-212445), on the model of `/configuration/hosts/*`.
export const serviceFormContactsEndpoint = '/configuration/services/contacts';
export const serviceFormContactGroupsEndpoint =
  '/configuration/services/contact_groups';
export const serviceFormTimePeriodsEndpoint =
  '/configuration/services/timeperiods';

// A number field holds `''` until something is typed.
type OptionalNumber = number | '';

// A switch in the form; Yes or No in a mass change, unpicked (`null`) until
// the user says, since a switch has no "unchanged".
type MassChangeBoolean = boolean | 'true' | 'false' | null;

const toApiBoolean = (value: MassChangeBoolean): boolean | null =>
  value === 'true' || value === 'false' ? value === 'true' : value;

const massChangeBooleanOptions = [
  { label: labelYes, value: 'true' },
  { label: labelNo, value: 'false' }
];

interface NotificationsValues {
  contactAdditiveInheritance: MassChangeBoolean;
  contactGroupAdditiveInheritance: MassChangeBoolean;
  contactGroups: Array<NamedEntity>;
  contacts: Array<NamedEntity>;
  enabled: TriState;
  firstDelay: OptionalNumber;
  interval: OptionalNumber;
  // The labels of the checked options; the API spells them as below.
  options: Array<string>;
  recoveryDelay: OptionalNumber;
  timeperiod: NamedEntity | null;
  // F16: the service notifies its host's contacts and contact groups only.
  useOnlyContactsFromHost: MassChangeBoolean;
}

// Optional: the section, and so its fields, does not exist on cloud.
interface NotificationsDetail {
  notifications?: NotificationsValues;
}

// Legacy `w,u,c,r,f,s,n`, spelled as the Hosts API spells its own options.
const notificationOptions: Array<[label: string, apiValue: string]> = [
  [labelWarning, 'warning'],
  [labelUnknown, 'unknown'],
  [labelCritical, 'critical'],
  [labelRecovery, 'recovery'],
  [labelFlapping, 'flapping'],
  [labelDowntimeScheduled, 'downtime_scheduled'],
  [labelNone, 'none']
];

const defaultNotifications: NotificationsValues = {
  contactAdditiveInheritance: false,
  contactGroupAdditiveInheritance: false,
  contactGroups: [],
  contacts: [],
  enabled: defaultTriState,
  firstDelay: '',
  interval: '',
  options: [],
  recoveryDelay: '',
  timeperiod: null,
  useOnlyContactsFromHost: false
};

// The detail endpoint leaves unset values out rather than sending null.
const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

const optionalBooleanDecoder = JsonDecoder.optional(JsonDecoder.boolean).map(
  (value): MassChangeBoolean => value ?? false
);

const optionalEntitiesDecoder = (name: string) =>
  JsonDecoder.optional(
    JsonDecoder.array(JsonDecoder.object(namedEntityDecoder, name), name)
  ).map((value) => value ?? []);

const notificationsDecoder = JsonDecoder.object<NotificationsValues>(
  {
    contactAdditiveInheritance: optionalBooleanDecoder,
    contactGroupAdditiveInheritance: optionalBooleanDecoder,
    contactGroups: optionalEntitiesDecoder('Contact group'),
    contacts: optionalEntitiesDecoder('Contact'),
    enabled: JsonDecoder.optional(triStateDecoder).map(
      (value) => value ?? defaultTriState
    ),
    firstDelay: optionalNumberDecoder,
    interval: optionalNumberDecoder,
    options: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.oneOf(
          notificationOptions.map(([label, apiValue]) =>
            JsonDecoder.isExactly(apiValue).map(() => label)
          ),
          'Notification option'
        ),
        'Notification options'
      )
    ).map((value) => value ?? []),
    recoveryDelay: optionalNumberDecoder,
    timeperiod: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(namedEntityDecoder, 'Period'))
    ).map((value) => value ?? null),
    useOnlyContactsFromHost: optionalBooleanDecoder
  },
  'Notifications',
  {
    contactAdditiveInheritance: 'contact_additive_inheritance',
    contactGroupAdditiveInheritance: 'contact_group_additive_inheritance',
    contactGroups: 'contact_groups',
    firstDelay: 'first_delay',
    recoveryDelay: 'recovery_delay',
    // Placeholder key, named after the legacy column
    // `service_use_only_contacts_from_host`.
    useOnlyContactsFromHost: 'use_only_contacts_from_host'
  }
);

const toApiNumber = (value: OptionalNumber | undefined): number | null =>
  value === '' || value === undefined ? null : Number(value);

const getDelaySchema = (message: string) =>
  number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(0, message);

const isUsingOnlyContactsFromHost = (values: FormikValues): boolean =>
  !!toApiBoolean(
    path(['notifications', 'useOnlyContactsFromHost'], values) ?? null
  );

const getNumberInput = ({
  fieldName,
  label
}: {
  fieldName: string;
  label: string;
}) => ({
  dataTestId: `service-form-notifications-${fieldName}`,
  fieldName: `notifications.${fieldName}`,
  label,
  text: { min: 0, type: 'number' },
  type: InputType.Text
});

export const notifications: FormSection<NotificationsDetail> = {
  defaultValues: { notifications: defaultNotifications },
  detailDecoders: {
    // A service whose notifications were never set has no such block at all.
    notifications: JsonDecoder.optional(
      JsonDecoder.nullable(notificationsDecoder)
    ).map((value) => value ?? defaultNotifications)
  },
  getInputs: ({ t, isAdditiveInheritanceEnabled, isMassChange }) => {
    const enabled = {
      dataTestId: 'service-form-notifications-enabled',
      fieldName: 'notifications.enabled',
      label: t(labelNotificationEnabled),
      segmentedButtons: { options: triStateOptions },
      type: InputType.SegmentedButtons
    };

    // Yes empties the service's own recipients, as legacy does in JS; the
    // server clears them too (D9).
    const useOnlyContactsFromHostField = {
      dataTestId: 'service-form-notifications-use-only-contacts-from-host',
      fieldName: 'notifications.useOnlyContactsFromHost',
      label: t(labelInheritContactsFromHost)
    };

    // In a mass change, Yes leaves each service's own recipients to the
    // server, which clears them (D9): emptying the lists here would only mean
    // "add nothing".
    const useOnlyContactsFromHost: InputPropsWithoutGroup = isMassChange
      ? {
          ...useOnlyContactsFromHostField,
          segmentedButtons: { options: massChangeBooleanOptions },
          type: InputType.SegmentedButtons
        }
      : {
          ...useOnlyContactsFromHostField,
          change: ({ setFieldValue, value }) => {
            setFieldValue('notifications.useOnlyContactsFromHost', value);

            if (!value) {
              return;
            }

            setFieldValue('notifications.contacts', []);
            setFieldValue('notifications.contactGroups', []);
            setFieldValue('notifications.contactAdditiveInheritance', false);
            setFieldValue(
              'notifications.contactGroupAdditiveInheritance',
              false
            );
          },
          type: InputType.Switch
        };

    const contacts = {
      connectedAutocomplete: buildSelector({
        baseEndpoint: servicesBaseEndpoint,
        chipColor: 'primary',
        endpoint: serviceFormContactsEndpoint,
        queryKey: 'service-form-contacts'
      }),
      dataTestId: 'service-form-notifications-contacts',
      fieldName: 'notifications.contacts',
      getDisabled: isUsingOnlyContactsFromHost,
      label: t(labelLinkedContacts),
      type: InputType.MultiConnectedAutocomplete
    };

    const contactGroups = {
      connectedAutocomplete: buildSelector({
        baseEndpoint: servicesBaseEndpoint,
        chipColor: 'primary',
        endpoint: serviceFormContactGroupsEndpoint,
        queryKey: 'service-form-contact-groups'
      }),
      dataTestId: 'service-form-notifications-contact-groups',
      fieldName: 'notifications.contactGroups',
      getDisabled: isUsingOnlyContactsFromHost,
      label: t(labelLinkedContactGroups),
      type: InputType.MultiConnectedAutocomplete
    };

    // Only the platform's vertical inheritance mode reads the additive
    // toggles; legacy hides them otherwise, and the server drops them.
    const withAdditiveToggle = (
      selector: typeof contacts,
      toggle: { dataTestId: string; fieldName: string; label: string }
    ) =>
      isAdditiveInheritanceEnabled
        ? {
            fieldName: `${selector.fieldName}-row`,
            grid: {
              alignItems: 'center',
              className: 'grid-cols-[1fr_auto]',
              columns: [
                selector,
                isMassChange
                  ? {
                      ...toggle,
                      getDisabled: isUsingOnlyContactsFromHost,
                      segmentedButtons: { options: massChangeBooleanOptions },
                      type: InputType.SegmentedButtons
                    }
                  : {
                      ...toggle,
                      getDisabled: isUsingOnlyContactsFromHost,
                      type: InputType.Switch
                    }
              ]
            },
            label: `${selector.dataTestId}-row`,
            type: InputType.Grid
          }
        : selector;

    const options = {
      // The group renders no label of its own.
      additionalLabel: t(labelNotificationOptions),
      dataTestId: 'service-form-notifications-options',
      exclusiveCheckboxGroup: {
        direction: 'horizontal' as const,
        exclusiveLabel: labelNone,
        exclusiveOption: labelNone,
        options: notificationOptions.map(([label]) => label),
        variant: 'chips' as const
      },
      fieldName: 'notifications.options',
      label: '',
      type: InputType.ExclusiveCheckboxGroup
    };

    const period = {
      connectedAutocomplete: buildSelector({
        baseEndpoint: servicesBaseEndpoint,
        endpoint: serviceFormTimePeriodsEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: 'service-form-notification-period'
      }),
      dataTestId: 'service-form-notifications-timeperiod',
      fieldName: 'notifications.timeperiod',
      label: t(labelNotificationPeriod),
      type: InputType.SingleConnectedAutocomplete
    };

    // Row by row: interval beside the first delay, period beside recovery.
    const delays = {
      fieldName: 'notifications-delays',
      grid: {
        className: 'grid-cols-2',
        columns: [
          getNumberInput({
            fieldName: 'interval',
            label: t(labelNotificationInterval)
          }),
          getNumberInput({
            fieldName: 'firstDelay',
            label: t(labelFirstNotificationDelay)
          }),
          period,
          getNumberInput({
            fieldName: 'recoveryDelay',
            label: t(labelRecoveryNotificationDelay)
          })
        ]
      },
      label: 'service-form-notifications-delays',
      type: InputType.Grid
    };

    // One column in a narrow panel, two once the panel leaves room for both.
    return [
      {
        fieldName: 'notifications-layout',
        grid: {
          className: 'grid-cols-1 gap-x-8 @[1100px]:grid-cols-2',
          columns: [
            {
              fieldName: 'notifications-recipients',
              grid: {
                className: 'grid-cols-1',
                columns: [
                  enabled,
                  useOnlyContactsFromHost,
                  withAdditiveToggle(contacts, {
                    dataTestId:
                      'service-form-notifications-contact-additive-inheritance',
                    fieldName: 'notifications.contactAdditiveInheritance',
                    label: t(labelContactAdditiveInheritance)
                  }),
                  withAdditiveToggle(contactGroups, {
                    dataTestId:
                      'service-form-notifications-contact-group-additive-inheritance',
                    fieldName: 'notifications.contactGroupAdditiveInheritance',
                    label: t(labelContactGroupAdditiveInheritance)
                  }),
                  // One choice for contacts and contact groups, as on Hosts.
                  ...(isMassChange ? [getUpdateModeInput('contacts', t)] : [])
                ]
              },
              label: 'service-form-notifications-recipients',
              type: InputType.Grid
            },
            {
              fieldName: 'notifications-when',
              grid: {
                className: 'grid-cols-1',
                columns: [
                  withUpdateMode({
                    field: 'notificationOptions',
                    input: options,
                    isMassChange,
                    t
                  }),
                  delays
                ]
              },
              label: 'service-form-notifications-when',
              type: InputType.Grid
            }
          ]
        },
        label: 'service-form-notifications-layout',
        type: InputType.Grid
      }
    ];
  },
  getSchema: ({ t }) => ({
    notifications: object({
      firstDelay: getDelaySchema(t(labelMustBePositiveIntegerOrZero)),
      interval: getDelaySchema(t(labelMustBePositiveIntegerOrZero)),
      recoveryDelay: getDelaySchema(t(labelMustBePositiveIntegerOrZero)),
      // `'true'` or `'false'` in a mass change, or `null` while unpicked.
      useOnlyContactsFromHost: mixed().nullable()
    })
  }),
  // The tab does not exist on cloud, where notifications keep their defaults.
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  label: labelNotifications,
  toPayload: (values, { isAdditiveInheritanceEnabled }) => {
    const notificationsValues = (values.notifications ??
      defaultNotifications) as NotificationsValues;
    const useOnlyContactsFromHost = toApiBoolean(
      notificationsValues.useOnlyContactsFromHost
    );

    return {
      notifications: {
        // Left to their server default where the form does not show them,
        // and off when the host's recipients replace the service's own.
        ...(isAdditiveInheritanceEnabled && {
          contact_additive_inheritance: useOnlyContactsFromHost
            ? false
            : toApiBoolean(notificationsValues.contactAdditiveInheritance),
          contact_group_additive_inheritance: useOnlyContactsFromHost
            ? false
            : toApiBoolean(notificationsValues.contactGroupAdditiveInheritance)
        }),
        contact_groups: useOnlyContactsFromHost
          ? []
          : toIds(notificationsValues.contactGroups),
        contacts: useOnlyContactsFromHost
          ? []
          : toIds(notificationsValues.contacts),
        enabled: notificationsValues.enabled,
        first_delay: toApiNumber(notificationsValues.firstDelay),
        interval: toApiNumber(notificationsValues.interval),
        options: notificationsValues.options.map(
          (label) =>
            notificationOptions.find(
              ([optionLabel]) => optionLabel === label
            )?.[1]
        ),
        recovery_delay: toApiNumber(notificationsValues.recoveryDelay),
        timeperiod_id: notificationsValues.timeperiod?.id ?? null,
        use_only_contacts_from_host: useOnlyContactsFromHost
      }
    };
  }
};
