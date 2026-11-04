import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { getCheckCommandBlock } from '../../../ResourceForm/fieldBlocks/checkCommand';
import {
  getActionUrlBlock,
  getNotesAndIconsBlock
} from '../../../ResourceForm/fieldBlocks/extendedInfos';
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
  hostTemplateFormCommandsEndpoint,
  hostTemplateFormHostCategoriesEndpoint,
  hostTemplateFormHostSeveritiesEndpoint,
  hostTemplateFormMediasEndpoint,
  hostTemplateFormServiceTemplatesEndpoint,
  hostTemplateFormTimePeriodsEndpoint,
  hostTemplatesBaseEndpoint,
  timezonesEndpoint
} from '../../api/endpoints';
import {
  labelHostCategories,
  labelHostCheckOptions,
  labelHostSeverity,
  labelLinkedServiceTemplates
} from '../../translatedLabels';
import { getHostTemplateIdentityBlock } from '../blocks/hostTemplateIdentity';
import { getLockedBlock } from '../blocks/locked';
import { getMacrosBlock } from '../blocks/macros';
import { getTemplatesBlock } from '../blocks/templates';

export const hostTemplateResource: ResourceOptions = {
  baseEndpoint: hostTemplatesBaseEndpoint,
  idPrefix: 'host-template-form'
};

const resource = hostTemplateResource;

// The blocks of fields the host template form edits. The sections it shows
// them in, and their order, are `../layout.ts`'s.
export const blocks = [
  getHostTemplateIdentityBlock({ resource }),
  getLockedBlock(),
  getCheckCommandBlock({
    endpoint: hostTemplateFormCommandsEndpoint,
    // A template's arguments may apply to the command its parents set.
    hasInheritedCommand: () => true,
    resource,
    title: labelHostCheckOptions
  }),
  getMacrosBlock({ resource }),
  getSnmpBlock({ resource }),
  getTemplatesBlock({ resource }),
  getRelationListBlock({
    decoderNames: ['Category', 'Categories'],
    endpoint: hostTemplateFormHostCategoriesEndpoint,
    id: 'categories',
    label: labelHostCategories,
    name: 'categories',
    payloadKey: 'category_ids',
    resource
  }),
  getSeverityBlock({
    endpoint: hostTemplateFormHostSeveritiesEndpoint,
    label: labelHostSeverity,
    resource
  }),
  getRelationListBlock({
    decoderNames: ['Service template', 'Service templates'],
    detailKey: 'linked_service_templates',
    endpoint: hostTemplateFormServiceTemplatesEndpoint,
    id: 'linkedServiceTemplates',
    label: labelLinkedServiceTemplates,
    name: 'linked-service-templates',
    payloadKey: 'linked_service_template_ids',
    resource
  }),
  getNotesAndIconsBlock({
    mediasEndpoint: hostTemplateFormMediasEndpoint,
    resource
  }),
  getActionUrlBlock({ resource }),
  getTimezoneBlock({ endpoint: timezonesEndpoint, resource }),
  getCheckPeriodAndIntervalsBlock({
    resource,
    timePeriodsEndpoint: hostTemplateFormTimePeriodsEndpoint
  }),
  getActiveAndPassiveChecksBlock({ resource })
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
export type HostTemplateDetail = DetailOfBlocks<typeof blocks>;

export type {
  BlockId,
  Blocks,
  FieldBlock,
  FormValues,
  PlatformContext,
  SectionContext
} from '../../../ResourceForm/models';
