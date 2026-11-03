import {
  getAvailableBlocks,
  getInputBlocks
} from '../../../ResourceForm/blocks';
import type {
  Blocks,
  DetailOfBlocks,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../../../ResourceForm/models';
import {
  serviceFormHostsEndpoint,
  servicesBaseEndpoint
} from '../../api/endpoints';
import { getServiceIdentityBlock } from '../blocks/serviceIdentity';

export const serviceResource: ResourceOptions = {
  baseEndpoint: servicesBaseEndpoint,
  idPrefix: 'service-form'
};

const resource = serviceResource;

// The blocks of fields the service form edits. The sections it shows them in,
// and their order, are `../layout.ts`'s.
export const blocks = [
  getServiceIdentityBlock({ hostsEndpoint: serviceFormHostsEndpoint, resource })
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
