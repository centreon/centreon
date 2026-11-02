import {
  getMassChangeAdapter as getBlocksMassChangeAdapter,
  getMassChangeDefaultValues as getBlocksMassChangeDefaultValues,
  getMassChangeWarning as getBlocksMassChangeWarning
} from '../../ResourceForm/massChange';
import { blocks, type PlatformContext } from './sections';

export const getMassChangeDefaultValues = (context: PlatformContext) =>
  getBlocksMassChangeDefaultValues(blocks, context);

// Replacing a host's templates deletes the services they created.
export const getMassChangeWarning = getBlocksMassChangeWarning(blocks);

export const getMassChangeAdapter = (context: PlatformContext) =>
  getBlocksMassChangeAdapter(blocks, context);
