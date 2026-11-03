import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { getCheckCommandBlock } from '../../../ResourceForm/fieldBlocks/checkCommand';
import { getRelationListBlock } from '../../../ResourceForm/fieldBlocks/relationList';
import {
  getActiveAndPassiveChecksBlock,
  getCheckPeriodAndIntervalsBlock
} from '../../../ResourceForm/fieldBlocks/scheduling';
import type {
  Blocks,
  DetailOfBlocks,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../../../ResourceForm/models';
import {
  serviceFormCommandsEndpoint,
  serviceFormHostsEndpoint,
  serviceFormServiceGroupsEndpoint,
  serviceFormTimePeriodsEndpoint,
  serviceFormTrapsEndpoint,
  servicesBaseEndpoint,
  serviceTemplatesEndpoint
} from '../../api/endpoints';
import { labelServiceGroups } from '../../translatedLabels';
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
  getCheckPeriodAndIntervalsBlock({
    resource,
    timePeriodsEndpoint: serviceFormTimePeriodsEndpoint
  }),
  getActiveAndPassiveChecksBlock({ resource, withVolatile: true }),
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
  getSnmpTrapsBlock({ endpoint: serviceFormTrapsEndpoint, resource })
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
