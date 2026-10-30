import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { object } from 'yup';

import {
  namedEntitiesDecoder,
  optionalNamedEntityDecoder
} from '../api/decoders';
import { getBlock, twoColumns } from '../grid';
import type {
  FieldBlock,
  NamedEntity,
  PlatformContext,
  ResourceOptions
} from '../models';
import {
  getIntegerSchema,
  type OptionalNumber,
  optionalNumberDecoder,
  toApiNumber
} from '../numbers';
import { buildSelector, toIds } from '../selector';
import {
  labelContactAdditiveInheritance,
  labelContactGroupAdditiveInheritance,
  labelContactsToNotify,
  labelFirstNotificationDelay,
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
  labelRecoveryNotificationDelay,
  labelYes
} from '../translatedLabels';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../triState';
import { getUpdateModeInput, withUpdateMode } from '../updateMode';

// The three blocks fill the API's `notifications`. Notifications are an on
// premise concern: none of them exists on cloud.
const isAvailable = ({ isCloudPlatform }: PlatformContext): boolean =>
  !isCloudPlatform;

// Optional: the blocks, and so their fields, do not exist on cloud.
interface NotificationsDetail<Values> {
  notifications?: Values;
}

// A host whose notifications were never set has no such object at all.
const getNotificationsDecoder = <Values>({
  decoders,
  defaultValues,
  keyMap
}: {
  decoders: JsonDecoder.DecoderObject<Values>;
  defaultValues: Values;
  keyMap?: JsonDecoder.DecoderObjectKeyMap<Values>;
}): JsonDecoder.DecoderObject<NotificationsDetail<Values>> =>
  ({
    notifications: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<Values>(decoders, 'Notifications', keyMap)
      )
    ).map((value) => value ?? defaultValues)
  }) as JsonDecoder.DecoderObject<NotificationsDetail<Values>>;

const getNotifications = <Values>(
  values: Record<string, unknown>,
  defaultValues: Values
): Values => (values.notifications ?? defaultValues) as Values;

// A mass change holds `'true'` / `'false'`, or null until one is picked.
type AdditiveInheritance = boolean | 'true' | 'false' | null;

interface RecipientsValues {
  contactAdditiveInheritance: AdditiveInheritance;
  contactGroupAdditiveInheritance: AdditiveInheritance;
  contactGroups: Array<NamedEntity>;
  contacts: Array<NamedEntity>;
  enabled: TriState;
}

const defaultRecipients: RecipientsValues = {
  contactAdditiveInheritance: false,
  contactGroupAdditiveInheritance: false,
  contactGroups: [],
  contacts: [],
  enabled: defaultTriState
};

const optionalBooleanDecoder = JsonDecoder.optional(JsonDecoder.boolean).map(
  (value) => value ?? false
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

// Whether to notify, and whom.
export const notificationRecipients = ({
  contactGroupsEndpoint,
  contactsEndpoint,
  resource: { baseEndpoint, idPrefix }
}: {
  contactGroupsEndpoint: string;
  contactsEndpoint: string;
  resource: ResourceOptions;
}): FieldBlock<NotificationsDetail<RecipientsValues>> => ({
  defaultValues: { notifications: defaultRecipients },
  detailDecoders: getNotificationsDecoder<RecipientsValues>({
    decoders: {
      contactAdditiveInheritance: optionalBooleanDecoder,
      contactGroupAdditiveInheritance: optionalBooleanDecoder,
      contactGroups: namedEntitiesDecoder('Contact group', 'Contact groups'),
      contacts: namedEntitiesDecoder('Contact', 'Contacts'),
      enabled: triStateDecoder
    },
    defaultValues: defaultRecipients,
    keyMap: {
      contactAdditiveInheritance: 'contact_additive_inheritance',
      contactGroupAdditiveInheritance: 'contact_group_additive_inheritance',
      contactGroups: 'contact_groups'
    }
  }),
  getInputs: ({ t, isAdditiveInheritanceEnabled, isMassChange }) => {
    const contacts = {
      connectedAutocomplete: buildSelector({
        baseEndpoint,
        chipColor: 'primary',
        endpoint: contactsEndpoint,
        queryKey: `${idPrefix}-contacts`
      }),
      dataTestId: `${idPrefix}-notifications-contacts`,
      fieldName: 'notifications.contacts',
      label: t(labelLinkedContacts),
      type: InputType.MultiConnectedAutocomplete
    };

    const contactGroups = {
      connectedAutocomplete: buildSelector({
        baseEndpoint,
        chipColor: 'primary',
        endpoint: contactGroupsEndpoint,
        queryKey: `${idPrefix}-contact-groups`
      }),
      dataTestId: `${idPrefix}-notifications-contact-groups`,
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

    return {
      notificationRecipients: getBlock({
        columns: [
          {
            additionalLabel: t(labelNotifications),
            dataTestId: `${idPrefix}-notifications-enabled`,
            fieldName: 'notifications.enabled',
            label: t(labelNotificationEnabled),
            segmentedButtons: { hideLabel: true, options: triStateOptions },
            type: InputType.SegmentedButtons
          },
          {
            // Over the whole row, its additive toggle included.
            ...withAdditiveToggle(contacts, {
              dataTestId: `${idPrefix}-notifications-contact-additive-inheritance`,
              fieldName: 'notifications.contactAdditiveInheritance',
              label: t(labelContactAdditiveInheritance)
            }),
            additionalLabel: t(labelContactsToNotify)
          },
          withAdditiveToggle(contactGroups, {
            dataTestId: `${idPrefix}-notifications-contact-group-additive-inheritance`,
            fieldName: 'notifications.contactGroupAdditiveInheritance',
            label: t(labelContactGroupAdditiveInheritance)
          }),
          // One choice for contacts and contact groups, as legacy's
          // `mc_mod_hcg`.
          ...(isMassChange
            ? [getUpdateModeInput({ field: 'contacts', idPrefix, t })]
            : [])
        ],
        idPrefix,
        name: 'notifications-recipients'
      })
    };
  },
  getSchema: () => ({}),
  isAvailable,
  massChange: {
    // So are the additive inheritances, a switch having no "unchanged".
    defaultValues: {
      notifications: {
        contactAdditiveInheritance: null,
        contactGroupAdditiveInheritance: null
      }
    },
    updateModeFields: {
      contacts: [
        ['notifications', 'contacts'],
        ['notifications', 'contact_groups']
      ]
    }
  },
  toPayload: (values, { isAdditiveInheritanceEnabled }) => {
    const notifications = getNotifications(values, defaultRecipients);

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
        enabled: notifications.enabled
      }
    };
  }
});

// What a resource notifies on, as labels and as the API spells them. `None`
// excludes the others.
export type NotificationOptions = Array<[label: string, apiValue: string]>;

// The events that trigger a notification: the resource's own set.
export const notifyOn = ({
  options,
  resource: { idPrefix }
}: {
  options: NotificationOptions;
  resource: ResourceOptions;
}): FieldBlock<NotificationsDetail<{ options: Array<string> }>> => ({
  defaultValues: { notifications: { options: [] } },
  detailDecoders: getNotificationsDecoder<{ options: Array<string> }>({
    decoders: {
      // The labels of the checked options.
      options: JsonDecoder.array(
        JsonDecoder.oneOf(
          options.map(([label, apiValue]) =>
            JsonDecoder.isExactly(apiValue).map(() => label)
          ),
          'Notification option'
        ),
        'Notification options'
      )
    },
    defaultValues: { options: [] }
  }),
  getInputs: ({ isMassChange, t }) => ({
    notifyOn: withUpdateMode({
      field: 'notificationOptions',
      idPrefix,
      input: {
        // The group renders no label of its own.
        additionalLabel: t(labelNotifyOn),
        dataTestId: `${idPrefix}-notifications-options`,
        exclusiveCheckboxGroup: {
          direction: 'horizontal' as const,
          exclusiveLabel: labelNone,
          exclusiveOption: labelNone,
          options: options.map(([label]) => label),
          variant: 'chips' as const
        },
        fieldName: 'notifications.options',
        label: '',
        type: InputType.ExclusiveCheckboxGroup
      },
      isMassChange,
      t
    })
  }),
  getSchema: () => ({}),
  isAvailable,
  massChange: {
    updateModeFields: { notificationOptions: [['notifications', 'options']] }
  },
  toPayload: (values) => ({
    notifications: {
      options: getNotifications(values, { options: [] }).options.map(
        (label) => options.find(([optionLabel]) => optionLabel === label)?.[1]
      )
    }
  })
});

interface DelaysValues {
  firstDelay: OptionalNumber;
  interval: OptionalNumber;
  recoveryDelay: OptionalNumber;
  timeperiod: NamedEntity | null;
}

const defaultDelays: DelaysValues = {
  firstDelay: '',
  interval: '',
  recoveryDelay: '',
  timeperiod: null
};

// When to notify: how often, during which period, after which delays.
export const notificationDelays = ({
  resource: { baseEndpoint, idPrefix },
  timePeriodsEndpoint
}: {
  resource: ResourceOptions;
  timePeriodsEndpoint: string;
}): FieldBlock<NotificationsDetail<DelaysValues>> => ({
  defaultValues: { notifications: defaultDelays },
  detailDecoders: getNotificationsDecoder<DelaysValues>({
    decoders: {
      firstDelay: optionalNumberDecoder,
      interval: optionalNumberDecoder,
      recoveryDelay: optionalNumberDecoder,
      timeperiod: optionalNamedEntityDecoder('Period')
    },
    defaultValues: defaultDelays,
    keyMap: { firstDelay: 'first_delay', recoveryDelay: 'recovery_delay' }
  }),
  getInputs: ({ t }) => {
    const getNumberInput = (fieldName: keyof DelaysValues, label: string) => ({
      dataTestId: `${idPrefix}-notifications-${fieldName}`,
      fieldName: `notifications.${fieldName}`,
      label: t(label),
      text: { min: 0, type: 'number' },
      type: InputType.Text
    });

    return {
      // Row by row: interval beside the first delay, period beside recovery.
      notificationDelays: getBlock({
        className: twoColumns,
        columnSeparator: true,
        columns: [
          getNumberInput('interval', labelNotificationInterval),
          getNumberInput('firstDelay', labelFirstNotificationDelay),
          {
            connectedAutocomplete: buildSelector({
              baseEndpoint,
              endpoint: timePeriodsEndpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              queryKey: `${idPrefix}-notification-period`
            }),
            dataTestId: `${idPrefix}-notifications-timeperiod`,
            fieldName: 'notifications.timeperiod',
            label: t(labelNotificationPeriod),
            type: InputType.SingleConnectedAutocomplete
          },
          getNumberInput('recoveryDelay', labelRecoveryNotificationDelay)
        ],
        idPrefix,
        name: 'notifications-delays'
      })
    };
  },
  getSchema: ({ t }) => {
    const positiveOrZero = () =>
      getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      });

    return {
      notifications: object({
        firstDelay: positiveOrZero(),
        interval: positiveOrZero(),
        recoveryDelay: positiveOrZero()
      })
    };
  },
  isAvailable,
  toPayload: (values) => {
    const notifications = getNotifications(values, defaultDelays);

    return {
      notifications: {
        first_delay: toApiNumber(notifications.firstDelay),
        interval: toApiNumber(notifications.interval),
        recovery_delay: toApiNumber(notifications.recoveryDelay),
        timeperiod_id: notifications.timeperiod?.id ?? null
      }
    };
  }
});
