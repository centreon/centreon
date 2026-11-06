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
import { hostTemplatesBaseEndpoint } from '../../api/endpoints';
import { getHostTemplateIdentityBlock } from '../blocks/hostTemplateIdentity';
import { getLockedBlock } from '../blocks/locked';

export const hostTemplateResource: ResourceOptions = {
  baseEndpoint: hostTemplatesBaseEndpoint,
  idPrefix: 'host-template-form'
};

const resource = hostTemplateResource;

// The blocks of fields the host template form edits. The sections it shows
// them in, and their order, are `../layout.ts`'s.
export const blocks = [
  getHostTemplateIdentityBlock({ resource }),
  getLockedBlock()
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
