import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object } from 'yup';

import {
  hostFormContactGroupsEndpoint,
  hostFormContactsEndpoint,
  hostFormTimePeriodsEndpoint
} from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelContactAdditiveInheritance,
  labelContactGroupAdditiveInheritance,
  labelDown,
  labelDowntimeScheduled,
  labelFirstNotificationDelay,
  labelFlapping,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelMustBePositiveIntegerOrZero,
  labelNone,
  labelNotification,
  labelNotificationEnabled,
  labelNotificationInterval,
  labelNotificationOptions,
  labelNotificationPeriod,
  labelRecovery,
  labelRecoveryNotificationDelay,
  labelUnreachable
} from '../../translatedLabels';
import { buildSelector, toIds } from '../selector';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../triState';
import { getUpdateModeInput, withUpdateMode } from '../updateMode';
import type { FormSection } from './models';

// A number field holds `''` until something is typed.
type OptionalNumber = number | '';

interface NotificationsValues {
  contactAdditiveInheritance: boolean;
  contactGroupAdditiveInheritance: boolean;
  contactGroups: Array<NamedEntity>;
  contacts: Array<NamedEntity>;
  enabled: TriState;
  firstDelay: OptionalNumber;
  interval: OptionalNumber;
  // The labels of the checked options; the API spells them as below.
  options: Array<string>;
  recoveryDelay: OptionalNumber;
  timeperiod: NamedEntity | null;
}

// Optional: the section, and so its fields, does not exist on cloud.
interface NotificationDetail {
  notifications?: NotificationsValues;
}

const notificationOptions: Array<[label: string, apiValue: string]> = [
  [labelDown, 'down'],
  [labelUnreachable, 'unreachable'],
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
  timeperiod: null
};

// The detail endpoint leaves unset values out rather than sending null.
const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

const optionalBooleanDecoder = JsonDecoder.optional(JsonDecoder.boolean).map(
  (value) => value ?? false
);

const notificationsDecoder = JsonDecoder.object<NotificationsValues>(
  {
    contactAdditiveInheritance: optionalBooleanDecoder,
    contactGroupAdditiveInheritance: optionalBooleanDecoder,
    contactGroups: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Contact group'),
      'Contact groups'
    ),
    contacts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Contact'),
      'Contacts'
    ),
    enabled: triStateDecoder,
    firstDelay: optionalNumberDecoder,
    interval: optionalNumberDecoder,
    options: JsonDecoder.array(
      JsonDecoder.oneOf(
        notificationOptions.map(([label, apiValue]) =>
          JsonDecoder.isExactly(apiValue).map(() => label)
        ),
        'Notification option'
      ),
      'Notification options'
    ),
    recoveryDelay: optionalNumberDecoder,
    timeperiod: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(namedEntityDecoder, 'Period'))
    ).map((value) => value ?? null)
  },
  'Notifications',
  {
    contactAdditiveInheritance: 'contact_additive_inheritance',
    contactGroupAdditiveInheritance: 'contact_group_additive_inheritance',
    contactGroups: 'contact_groups',
    firstDelay: 'first_delay',
    recoveryDelay: 'recovery_delay'
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

const getNumberInput = ({
  fieldName,
  label
}: {
  fieldName: string;
  label: string;
}) => ({
  dataTestId: `host-form-notifications-${fieldName}`,
  fieldName: `notifications.${fieldName}`,
  label,
  text: { min: 0, type: 'number' },
  type: InputType.Text
});

export const notification: FormSection<NotificationDetail> = {
  defaultValues: { notifications: defaultNotifications },
  detailDecoders: {
    // A host whose notifications were never set has no such block at all.
    notifications: JsonDecoder.optional(
      JsonDecoder.nullable(notificationsDecoder)
    ).map((value) => value ?? defaultNotifications)
  },
  getInputs: ({ t, isAdditiveInheritanceEnabled, isMassChange }) => {
    const enabled = {
      dataTestId: 'host-form-notifications-enabled',
      fieldName: 'notifications.enabled',
      label: t(labelNotificationEnabled),
      segmentedButtons: { options: triStateOptions },
      type: InputType.SegmentedButtons
    };

    const contacts = {
      connectedAutocomplete: buildSelector({
        chipColor: 'primary',
        endpoint: hostFormContactsEndpoint,
        queryKey: 'host-form-contacts'
      }),
      dataTestId: 'host-form-notifications-contacts',
      fieldName: 'notifications.contacts',
      label: t(labelLinkedContacts),
      type: InputType.MultiConnectedAutocomplete
    };

    const contactGroups = {
      connectedAutocomplete: buildSelector({
        chipColor: 'primary',
        endpoint: hostFormContactGroupsEndpoint,
        queryKey: 'host-form-contact-groups'
      }),
      dataTestId: 'host-form-notifications-contact-groups',
      fieldName: 'notifications.contactGroups',
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
              columns: [selector, { ...toggle, type: InputType.Switch }]
            },
            label: `${selector.dataTestId}-row`,
            type: InputType.Grid
          }
        : selector;

    const options = {
      // The group renders no label of its own.
      additionalLabel: t(labelNotificationOptions),
      dataTestId: 'host-form-notifications-options',
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
        endpoint: hostFormTimePeriodsEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: 'host-form-notification-period'
      }),
      dataTestId: 'host-form-notifications-timeperiod',
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
      label: 'host-form-notifications-delays',
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
                  withAdditiveToggle(contacts, {
                    dataTestId:
                      'host-form-notifications-contact-additive-inheritance',
                    fieldName: 'notifications.contactAdditiveInheritance',
                    label: t(labelContactAdditiveInheritance)
                  }),
                  withAdditiveToggle(contactGroups, {
                    dataTestId:
                      'host-form-notifications-contact-group-additive-inheritance',
                    fieldName: 'notifications.contactGroupAdditiveInheritance',
                    label: t(labelContactGroupAdditiveInheritance)
                  }),
                  // One choice for contacts and contact groups, as legacy's
                  // `mc_mod_hcg`.
                  ...(isMassChange ? [getUpdateModeInput('contacts', t)] : [])
                ]
              },
              label: 'host-form-notifications-recipients',
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
              label: 'host-form-notifications-when',
              type: InputType.Grid
            }
          ]
        },
        label: 'host-form-notifications-layout',
        type: InputType.Grid
      }
    ];
  },
  getSchema: ({ t }) => ({
    notifications: object({
      firstDelay: getDelaySchema(t(labelMustBePositiveIntegerOrZero)),
      interval: getDelaySchema(t(labelMustBePositiveIntegerOrZero)),
      recoveryDelay: getDelaySchema(t(labelMustBePositiveIntegerOrZero))
    })
  }),
  // Notifications are an onPrem concern; the US has no such tab on cloud.
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  label: labelNotification,
  toPayload: (values, { isAdditiveInheritanceEnabled }) => {
    const notifications = (values.notifications ??
      defaultNotifications) as NotificationsValues;

    return {
      notifications: {
        // Left to their server default where the form does not show them.
        ...(isAdditiveInheritanceEnabled && {
          contact_additive_inheritance:
            notifications.contactAdditiveInheritance,
          contact_group_additive_inheritance:
            notifications.contactGroupAdditiveInheritance
        }),
        contact_groups: toIds(notifications.contactGroups),
        contacts: toIds(notifications.contacts),
        enabled: notifications.enabled,
        first_delay: toApiNumber(notifications.firstDelay),
        interval: toApiNumber(notifications.interval),
        options: notifications.options.map(
          (label) =>
            notificationOptions.find(
              ([optionLabel]) => optionLabel === label
            )?.[1]
        ),
        recovery_delay: toApiNumber(notifications.recoveryDelay),
        timeperiod_id: notifications.timeperiod?.id ?? null
      }
    };
  }
};
