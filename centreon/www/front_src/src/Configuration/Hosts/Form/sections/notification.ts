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
  labelContactsToNotify,
  labelDown,
  labelDowntimeScheduled,
  labelFirstNotificationDelay,
  labelFlapping,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelMustBePositiveIntegerOrZero,
  labelNo,
  labelNone,
  labelNotificationEnabled,
  labelNotificationInterval,
  labelNotificationPeriod,
  labelNotifications,
  labelNotifyOn,
  labelRecovery,
  labelRecoveryNotificationDelay,
  labelUnreachable,
  labelYes
} from '../../translatedLabels';
import { getBlock, twoColumns } from '../grid';
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

// A mass change holds `'true'` / `'false'`, or null until one is picked.
type AdditiveInheritance = boolean | 'true' | 'false' | null;

interface NotificationsValues {
  contactAdditiveInheritance: AdditiveInheritance;
  contactGroupAdditiveInheritance: AdditiveInheritance;
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

const toApiBoolean = (value: AdditiveInheritance): boolean | null => {
  if (value === 'true' || value === 'false') {
    return value === 'true';
  }

  return value;
};

// A switch cannot say "leave it": in a mass change, nothing picked is
// unchanged, as legacy's Yes / No radio.
const additiveInheritanceOptions = [
  { label: labelYes, value: 'true' },
  { label: labelNo, value: 'false' }
];

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
      additionalLabel: t(labelNotifications),
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
              columns: [
                selector,
                isMassChange
                  ? {
                      ...toggle,
                      segmentedButtons: { options: additiveInheritanceOptions },
                      type: InputType.SegmentedButtons
                    }
                  : { ...toggle, type: InputType.Switch }
              ]
            },
            label: `${selector.dataTestId}-row`,
            type: InputType.Grid
          }
        : selector;

    const options = {
      // The group renders no label of its own.
      additionalLabel: t(labelNotifyOn),
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
    const delays = getBlock({
      className: twoColumns,
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
      ],
      name: 'notifications-delays'
    });

    return {
      notificationDelays: delays,
      notificationRecipients: getBlock({
        columns: [
          enabled,
          {
            // Over the whole row, its additive toggle included.
            ...withAdditiveToggle(contacts, {
              dataTestId:
                'host-form-notifications-contact-additive-inheritance',
              fieldName: 'notifications.contactAdditiveInheritance',
              label: t(labelContactAdditiveInheritance)
            }),
            additionalLabel: t(labelContactsToNotify)
          },
          withAdditiveToggle(contactGroups, {
            dataTestId:
              'host-form-notifications-contact-group-additive-inheritance',
            fieldName: 'notifications.contactGroupAdditiveInheritance',
            label: t(labelContactGroupAdditiveInheritance)
          }),
          // One choice for contacts and contact groups, as legacy's
          // `mc_mod_hcg`.
          ...(isMassChange ? [getUpdateModeInput('contacts', t)] : [])
        ],
        name: 'notifications-recipients'
      }),
      notifyOn: withUpdateMode({
        field: 'notificationOptions',
        input: options,
        isMassChange,
        t
      })
    };
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
  toPayload: (values, { isAdditiveInheritanceEnabled }) => {
    const notifications = (values.notifications ??
      defaultNotifications) as NotificationsValues;

    return {
      notifications: {
        // Left to their server default where the form does not show them.
        ...(isAdditiveInheritanceEnabled && {
          contact_additive_inheritance: toApiBoolean(
            notifications.contactAdditiveInheritance
          ),
          contact_group_additive_inheritance: toApiBoolean(
            notifications.contactGroupAdditiveInheritance
          )
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
