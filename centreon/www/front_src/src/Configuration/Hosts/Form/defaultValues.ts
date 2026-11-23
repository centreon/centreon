import { getDefaultValues as getBlocksDefaultValues } from '../../ResourceForm/blocks';
import { blocks, type PlatformContext } from './sections';

export const getDefaultValues = (context: PlatformContext) =>
  getBlocksDefaultValues(blocks, context);
