import { array } from 'yup';

import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { getCheckCommandBlock } from '../../../ResourceForm/fieldBlocks/checkCommand';
import {
  getAcknowledgmentTimeoutBlock,
  getEventHandlerBlock,
  getFlappingBlock,
  getFreshnessBlock
} from '../../../ResourceForm/fieldBlocks/dataProcessing';
import {
  getActionUrlAndGeoCoordinatesBlock,
  getCommentBlock,
  getNotesAndIconsBlock
} from '../../../ResourceForm/fieldBlocks/extendedInfos';
import { getMacrosBlock } from '../../../ResourceForm/fieldBlocks/macros';
import {
  getNotificationDelaysBlock,
  getNotificationRecipientsBlock,
  getNotifyOnBlock,
  type NotificationOptions
} from '../../../ResourceForm/fieldBlocks/notifications';
import { getRelationListBlock } from '../../../ResourceForm/fieldBlocks/relationList';
import {
  getActiveAndPassiveChecksBlock,
  getCheckPeriodAndIntervalsBlock
} from '../../../ResourceForm/fieldBlocks/scheduling';
import { getSeverityBlock } from '../../../ResourceForm/fieldBlocks/severity';
import { getSnmpBlock } from '../../../ResourceForm/fieldBlocks/snmp';
import { getTimezoneBlock } from '../../../ResourceForm/fieldBlocks/timezone';
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
import { getHostIdentityBlock } from '../blocks/hostIdentity';
import { getTemplatesBlock } from '../blocks/templates';
import HostMacros from '../HostMacros';

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
  getHostIdentityBlock({ pollersEndpoint: hostFormPollersEndpoint, resource }),
  getCheckCommandBlock({
    endpoint: hostFormCommandsEndpoint,
    resource,
    title: labelHostCheckOptions
  }),
  getMacrosBlock({ Component: HostMacros, prefix: '$_HOST', resource }),
  getSnmpBlock({ resource }),
  getTemplatesBlock({ resource }),
  getTimezoneBlock({ endpoint: timezonesEndpoint, resource }),
  getCheckPeriodAndIntervalsBlock({
    resource,
    timePeriodsEndpoint: hostFormTimePeriodsEndpoint
  }),
  getActiveAndPassiveChecksBlock({ resource }),
  getNotificationRecipientsBlock({
    contactGroupsEndpoint: hostFormContactGroupsEndpoint,
    contactsEndpoint: hostFormContactsEndpoint,
    resource
  }),
  getNotifyOnBlock({ options: hostNotificationOptions, resource }),
  getNotificationDelaysBlock({
    resource,
    timePeriodsEndpoint: hostFormTimePeriodsEndpoint
  }),
  getRelationListBlock({
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
  getRelationListBlock({
    decoderNames: ['Category', 'Categories'],
    endpoint: hostFormHostCategoriesEndpoint,
    id: 'categories',
    label: labelHostCategories,
    name: 'categories',
    payloadKey: 'category_ids',
    resource
  }),
  getRelationListBlock({
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
  getRelationListBlock({
    decoderNames: ['Child host', 'Child hosts'],
    detailKey: 'child_hosts',
    endpoint: hostFormHostsEndpoint,
    id: 'childHosts',
    label: labelChildHosts,
    name: 'child-hosts',
    payloadKey: 'child_host_ids',
    resource
  }),
  getSeverityBlock({
    endpoint: hostFormHostSeveritiesEndpoint,
    label: labelHostSeverity,
    resource
  }),
  getEventHandlerBlock({
    commandsEndpoint: hostFormCommandsEndpoint,
    resource
  }),
  getFreshnessBlock({ resource }),
  getAcknowledgmentTimeoutBlock({ resource }),
  getFlappingBlock({ resource }),
  getNotesAndIconsBlock({ mediasEndpoint: hostFormMediasEndpoint, resource }),
  getActionUrlAndGeoCoordinatesBlock({ resource }),
  getCommentBlock({ resource })
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
