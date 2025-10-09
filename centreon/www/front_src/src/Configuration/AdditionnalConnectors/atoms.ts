import { atomWithStorage } from 'jotai/utils';
import { columnsAtomKey } from './utils';

export const selectedColumnIdsAtom = atomWithStorage(columnsAtomKey, []);
