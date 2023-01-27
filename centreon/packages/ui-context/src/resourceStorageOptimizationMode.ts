import { atom } from 'jotai';

import { defaultResourceStorageOptimizationMode } from './defaults';

const resourceStorageOptimizationModeAtom = atom(
  defaultResourceStorageOptimizationMode
);

export default resourceStorageOptimizationModeAtom;
