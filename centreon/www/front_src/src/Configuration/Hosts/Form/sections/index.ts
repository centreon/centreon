import { array } from 'yup';

import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { checkCommand } from '../../../ResourceForm/fieldBlocks/checkCommand';
import {
  acknowledgmentTimeout,
  eventHandler,
  flapping,
  freshness
} from '../../../ResourceForm/fieldBlocks/dataProcessing';
import {
  actionUrlAndGeoCoordinates,
  comment,
  notesAndIcons
} from '../../../ResourceForm/fieldBlocks/extendedInfos';
import {
  type NotificationOptions,
  notificationDelays,
  notificationRecipients,
  notifyOn
} from '../../../ResourceForm/fieldBlocks/notifications';
import { relationList } from '../../../ResourceForm/fieldBlocks/relationList';
import {
  activeAndPassiveChecks,
  checkPeriodAndIntervals
} from '../../../ResourceForm/fieldBlocks/scheduling';
import { severity } from '../../../ResourceForm/fieldBlocks/severity';
import { snmp } from '../../../ResourceForm/fieldBlocks/snmp';
import { timezone } from '../../../ResourceForm/fieldBlocks/timezone';
import type {
  Blocks,
  DetailOfBlocks,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../../../ResourceForm/models';
import {
  hostFormCommandsEndpoint,
  hostFormContactGroupsEndpoint,
  hostFormContactsEndpoint,
  hostFormHostCategoriesEndpoint,
  hostFormHostGroupsEndpoint,
  hostFormHostSeveritiesEndpoint,
  hostFormHostsEndpoint,
  hostFormMediasEndpoint,
  hostFormPollersEndpoint,
  hostFormTimePeriodsEndpoint,
  hostsBaseEndpoint,
  timezonesEndpoint
} from '../../api/endpoints';
import {
  labelChildHosts,
  labelDown,
  labelDowntimeScheduled,
  labelFlapping,
  labelHostCategories,
  labelHostCheckOptions,
  labelHostGroups,
  labelHostSeverity,
  labelNone,
  labelParentAndChildHost,
  labelParentHosts,
  labelRecovery,
  labelUnreachable
} from '../../translatedLabels';
import { hostIdentity } from '../blocks/hostIdentity';
import { macros } from '../blocks/macros';
import { templates } from '../blocks/templates';

export const hostResource: ResourceOptions = {
  baseEndpoint: hostsBaseEndpoint,
  idPrefix: 'host-form'
};

const resource = hostResource;

const hostNotificationOptions: NotificationOptions = [
  [labelDown, 'down'],
  [labelUnreachable, 'unreachable'],
  [labelRecovery, 'recovery'],
  [labelFlapping, 'flapping'],
  [labelDowntimeScheduled, 'downtime_scheduled'],
  [labelNone, 'none']
];

// The blocks of fields the host form edits. The sections it shows them in,
// and their order, are `../layout.ts`'s.
export const blocks = [
  hostIdentity({ pollersEndpoint: hostFormPollersEndpoint, resource }),
  checkCommand({
    endpoint: hostFormCommandsEndpoint,
    resource,
    title: labelHostCheckOptions
  }),
  macros({ resource }),
  snmp({ resource }),
  templates({ resource }),
  timezone({ endpoint: timezonesEndpoint, resource }),
  checkPeriodAndIntervals({
    resource,
    timePeriodsEndpoint: hostFormTimePeriodsEndpoint
  }),
  activeAndPassiveChecks({ resource }),
  notificationRecipients({
    contactGroupsEndpoint: hostFormContactGroupsEndpoint,
    contactsEndpoint: hostFormContactsEndpoint,
    resource
  }),
  notifyOn({ options: hostNotificationOptions, resource }),
  notificationDelays({
    resource,
    timePeriodsEndpoint: hostFormTimePeriodsEndpoint
  }),
  relationList({
    decoderNames: ['Group', 'Groups'],
    endpoint: hostFormHostGroupsEndpoint,
    id: 'groups',
    // `CreateHostInput` counts at least one group on a cloud platform and
    // leaves it optional elsewhere: `WhenPlatform(forCloud: true)`.
    isRequired: ({ isCloudPlatform }) => isCloudPlatform,
    label: labelHostGroups,
    name: 'groups',
    payloadKey: 'host_group_ids',
    resource
  }),
  relationList({
    decoderNames: ['Category', 'Categories'],
    endpoint: hostFormHostCategoriesEndpoint,
    id: 'categories',
    label: labelHostCategories,
    name: 'categories',
    payloadKey: 'category_ids',
    resource
  }),
  relationList({
    decoderNames: ['Parent host', 'Parent hosts'],
    detailKey: 'parent_hosts',
    endpoint: hostFormHostsEndpoint,
    // As the legacy form, a host cannot be both parent and child.
    getSchema: ({ t }) =>
      array().test(
        'is-not-also-a-child',
        t(labelParentAndChildHost),
        (parents, { parent: values }) =>
          !(parents ?? []).some(({ id }) =>
            (values.childHosts ?? []).some(
              (child: { id: number }) => child.id === id
            )
          )
      ),
    id: 'parentHosts',
    label: labelParentHosts,
    name: 'parent-hosts',
    payloadKey: 'parent_host_ids',
    resource
  }),
  relationList({
    decoderNames: ['Child host', 'Child hosts'],
    detailKey: 'child_hosts',
    endpoint: hostFormHostsEndpoint,
    id: 'childHosts',
    label: labelChildHosts,
    name: 'child-hosts',
    payloadKey: 'child_host_ids',
    resource
  }),
  severity({
    endpoint: hostFormHostSeveritiesEndpoint,
    label: labelHostSeverity,
    resource
  }),
  eventHandler({ commandsEndpoint: hostFormCommandsEndpoint, resource }),
  freshness({ resource }),
  acknowledgmentTimeout({ resource }),
  flapping({ resource }),
  notesAndIcons({ mediasEndpoint: hostFormMediasEndpoint, resource }),
  actionUrlAndGeoCoordinates({ resource }),
  comment({ resource })
] as const;

// Each available block, as a section of the data.
export const getAvailableSections = (
  context: PlatformContext
): Array<{ section: (typeof blocks)[number] }> =>
  getAvailableBlocks(blocks, context).map((section) => ({ section }));

// Every input block the available blocks hand over.
export const getBlocks = (context: SectionContext): Blocks =>
  getInputBlocks(blocks, context);

// What the form opens on: the fields every block reads back.
export type HostDetail = DetailOfBlocks<typeof blocks>;

export type {
  BlockId,
  Blocks,
  FieldBlock,
  FormValues,
  PlatformContext,
  SectionContext
} from '../../../ResourceForm/models';
