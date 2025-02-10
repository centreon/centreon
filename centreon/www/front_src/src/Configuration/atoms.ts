import { atom } from 'jotai';
import { Configuration } from './models';

export const configurationAtom = atom<Configuration | null>({
  resourceType: null,
  endpoints: null
});
