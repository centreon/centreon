import {
  isUnchangedInherited,
  type MacroRow
} from '../../../ConfigurationBase/Form/Macros';
import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import { getCheckCommandBlock } from '../../../ResourceForm/fieldBlocks/checkCommand';
import { getMacrosBlock } from '../../../ResourceForm/fieldBlocks/macros';
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
import { labelRequired } from '../../../ResourceForm/translatedLabels';
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
import { getServiceIdentityBlock } from '../blocks/serviceIdentity';
import { getSnmpTrapsBlock } from '../blocks/snmpTraps';
import { getTemplateBlock } from '../blocks/template';
import { hasServiceTemplate } from '../inheritance';
import ServiceMacros from '../ServiceMacros';

export const serviceResource: ResourceOptions = {
  baseEndpoint: servicesBaseEndpoint,
  idPrefix: 'service-form'
};

const resource = serviceResource;

// Where the services API keeps what the host API calls its scheduling options.
const monitoringEngineGroup = 'monitoring_engine';

// A value, unless the row is still what the service inherits (not sent) or a
// stored password left as is (sent as null, the server keeps it).
const isMissingValue = (macro: MacroRow): boolean =>
  !isUnchangedInherited(macro) &&
  !(macro.hasStoredPassword && !macro.isErased) &&
  (macro.value ?? '') === '';

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
  getMacrosBlock({
    Component: ServiceMacros,
    getSchema: (schema, { t }) =>
      schema.test('values-required', function checkValuesRequired(macros) {
        const index = ((macros ?? []) as Array<MacroRow>).findIndex(
          isMissingValue
        );

        return (
          index === -1 ||
          this.createError({
            message: t(labelRequired),
            path: `${this.path}[${index}].value`
          })
        );
      }),
    prefix: '$_SERVICE',
    reservedNames: ['$_SERVICECRITICALITY_LEVEL$', '$_SERVICECRITICALITY_ID$'],
    resource
  }),
  getCheckPeriodAndIntervalsBlock({
    apiGroup: monitoringEngineGroup,
    resource,
    timePeriodsEndpoint: serviceFormTimePeriodsEndpoint
  }),
  getActiveAndPassiveChecksBlock({
    apiGroup: monitoringEngineGroup,
    resource,
    withVolatile: true
  }),
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
