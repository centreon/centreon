import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { getCheckCommandBlock } from '../../../ResourceForm/fieldBlocks/checkCommand';
import {
  getActionUrlAndGeoCoordinatesBlock,
  getCommentBlock,
  getNotesAndIconsBlock
} from '../../../ResourceForm/fieldBlocks/extendedInfos';
import { getRelationListBlock } from '../../../ResourceForm/fieldBlocks/relationList';
import {
  getActiveAndPassiveChecksBlock,
  getCheckPeriodAndIntervalsBlock
} from '../../../ResourceForm/fieldBlocks/scheduling';
import { getSeverityBlock } from '../../../ResourceForm/fieldBlocks/severity';
import type {
  Blocks,
  DetailOfBlocks,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../../../ResourceForm/models';
import {
  serviceFormCommandsEndpoint,
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
