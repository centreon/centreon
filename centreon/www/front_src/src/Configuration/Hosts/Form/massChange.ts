import {
  getMassChangeAdapter as getBlocksMassChangeAdapter,
  getMassChangeDefaultValues as getBlocksMassChangeDefaultValues,
  getBlockWarnings,
  joinWarnings
} from '../../ResourceForm/massChange';
import type { MassChangeWarningContext } from '../../ResourceForm/models';
import { blocks, type PlatformContext } from './sections';

export const getMassChangeDefaultValues = (context: PlatformContext) =>
  getBlocksMassChangeDefaultValues(blocks, context);

// Replacing a host's templates deletes the services they created.
export const getMassChangeWarning = ({
  t,
  values
}: MassChangeWarningContext): string | null =>
  joinWarnings(getBlockWarnings({ blocks, t, values }));

export const getMassChangeAdapter = (context: PlatformContext) =>
  getBlocksMassChangeAdapter(blocks, context);
