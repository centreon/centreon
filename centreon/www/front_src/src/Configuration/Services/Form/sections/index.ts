import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { getCheckCommandBlock } from '../../../ResourceForm/fieldBlocks/checkCommand';
import {
  getAcknowledgmentTimeoutBlock,
  getEventHandlerBlock,
  getFlappingBlock,
  getFreshnessBlock,
  getObsessRetainAndStalkingBlock
} from '../../../ResourceForm/fieldBlocks/dataProcessing';
import {
  getActionUrlAndGeoCoordinatesBlock,
  getCommentBlock,
  getNotesAndIconsBlock
} from '../../../ResourceForm/fieldBlocks/extendedInfos';
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
import type {
  Blocks,
  DetailOfBlocks,
  FormValues,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../../../ResourceForm/models';
import {
  labelCritical,
  labelNone,
  labelUnknown,
  labelWarning
} from '../../../ResourceForm/translatedLabels';
import {
  serviceFormCommandsEndpoint,
  serviceFormContactGroupsEndpoint,
  serviceFormContactsEndpoint,
  serviceFormGraphTemplatesEndpoint,
  serviceFormHostsEndpoint,
  serviceFormMediasEndpoint,
  serviceFormServiceCategoriesEndpoint,
  serviceFormServiceGroupsEndpoint,
  serviceFormServiceSeveritiesEndpoint,
  serviceFormTimePeriodsEndpoint,
  serviceFormTrapsEndpoint,
  servicesBaseEndpoint,
  serviceTemplatesEndpoint
} from '../../api/endpoints';
import {
  labelDowntimeScheduled,
  labelFlapping,
  labelMustBeIntegerBetweenZeroAndOneHundred,
  labelRecovery,
  labelServiceCategories,
  labelServiceGroups,
  labelServiceSeverity
} from '../../translatedLabels';
import { getGraphTemplateBlock } from '../blocks/graphTemplate';
import { getMacrosBlock } from '../blocks/macros';
import { getServiceIdentityBlock } from '../blocks/serviceIdentity';
import { getSnmpTrapsBlock } from '../blocks/snmpTraps';
import { getTemplateBlock } from '../blocks/template';
import { hasServiceTemplate } from '../inheritance';

export const serviceResource: ResourceOptions = {
  baseEndpoint: servicesBaseEndpoint,
  idPrefix: 'service-form'
};

const resource = serviceResource;

// Legacy `w,u,c,r,f,s,n`, spelled as the Hosts API spells its own options
// (placeholder values until the service API is there).
const serviceNotificationOptions: NotificationOptions = [
  [labelWarning, 'warning'],
  [labelUnknown, 'unknown'],
  [labelCritical, 'critical'],
  [labelRecovery, 'recovery'],
  [labelFlapping, 'flapping'],
  [labelDowntimeScheduled, 'downtime_scheduled'],
  [labelNone, 'none']
];

// PLACEHOLDER: the flag is an assumption. The commands selector
// (`CommandChoicesOutput`) only gives an id and a name so far, while the
// backend tells such a command by its connector and name
// (`Command::isCentreonMonitoringAgent()`).
export const hasCentreonMonitoringAgentCommand = (
  values: FormValues
): boolean =>
  (
    values.checkOptions as {
      command?: { isCentreonMonitoringAgent?: boolean } | null;
    } | null
  )?.command?.isCentreonMonitoringAgent === true;

// The blocks of fields the service form edits. The sections it shows them in,
// and their order, are `../layout.ts`'s.
export const blocks = [
  getServiceIdentityBlock({
    hostsEndpoint: serviceFormHostsEndpoint,
    resource
  }),
  // Required without a template, whose command the service runs otherwise:
  // the usual case is a template's command with the service's own arguments.
  getCheckCommandBlock({
    endpoint: serviceFormCommandsEndpoint,
    hasInheritedCommand: hasServiceTemplate,
    isCommandRequired: true,
    resource,
    validateArguments: true
  }),
  getTemplateBlock({ resource, serviceTemplatesEndpoint }),
  getMacrosBlock({ resource }),
  // Unlike the host groups of a host, optional on a cloud platform too.
  getRelationListBlock({
    decoderNames: ['Service group', 'Service groups'],
    detailKey: 'service_groups',
    endpoint: serviceFormServiceGroupsEndpoint,
    id: 'groups',
    label: labelServiceGroups,
    name: 'service-groups',
    payloadKey: 'service_group_ids',
    resource
  }),
  getSnmpTrapsBlock({ endpoint: serviceFormTrapsEndpoint, resource }),
  // The user's visible categories only: the server keeps the others. A
  // detail without them opens on none, as the previous service form did.
  getRelationListBlock({
    decoderNames: ['Category', 'Categories'],
    endpoint: serviceFormServiceCategoriesEndpoint,
    id: 'categories',
    label: labelServiceCategories,
    name: 'categories',
    payloadKey: 'category_ids',
    resource,
    withOptionalDetailKey: true
  }),
  getSeverityBlock({
    endpoint: serviceFormServiceSeveritiesEndpoint,
    label: labelServiceSeverity,
    resource,
    // As the legacy service form shows a severity.
    withLevel: true
  }),
  getGraphTemplateBlock({
    endpoint: serviceFormGraphTemplatesEndpoint,
    resource
  }),
  getNotesAndIconsBlock({
    mediasEndpoint: serviceFormMediasEndpoint,
    resource
  }),
  getActionUrlAndGeoCoordinatesBlock({ resource }),
  getCheckPeriodAndIntervalsBlock({
    resource,
    timePeriodsEndpoint: serviceFormTimePeriodsEndpoint
  }),
  getActiveAndPassiveChecksBlock({ resource, withVolatile: true }),
  getNotificationRecipientsBlock({
    contactGroupsEndpoint: serviceFormContactGroupsEndpoint,
    contactsEndpoint: serviceFormContactsEndpoint,
    resource,
    // The service detail route is a placeholder: unlike the hosts one, it is
    // not known to send every key, so a missing one keeps its default.
    withOptionalDetailKeys: true,
    withUseOnlyContactsFromHost: true
  }),
  getNotifyOnBlock({
    options: serviceNotificationOptions,
    resource,
    withOptionalDetailKeys: true
  }),
  getNotificationDelaysBlock({
    resource,
    timePeriodsEndpoint: serviceFormTimePeriodsEndpoint
  }),
  getEventHandlerBlock({
    commandsEndpoint: serviceFormCommandsEndpoint,
    resource,
    withArgumentsRules: true
  }),
  getFreshnessBlock({ isForced: hasCentreonMonitoringAgentCommand, resource }),
  getAcknowledgmentTimeoutBlock({ resource }),
  getFlappingBlock({
    maxThreshold: {
      max: 100,
      message: labelMustBeIntegerBetweenZeroAndOneHundred
    },
    resource
  }),
  getObsessRetainAndStalkingBlock({ resource }),
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
export type ServiceDetail = DetailOfBlocks<typeof blocks>;

export type {
  BlockId,
  Blocks,
  FieldBlock,
  FormValues,
  PlatformContext,
  SectionContext
} from '../../../ResourceForm/models';
