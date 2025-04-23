import { atom } from 'jotai';

import { AdditionalResource } from './types';

export const additionalResourcesAtom = atom<Array<AdditionalResource>>([]);
