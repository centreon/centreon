import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../../../../storage';

import { DefaultParameters, TokenFilter } from './models';

export const currentFilterAtom = atomWithStorage<TokenFilter>(
  `${baseKey}tokens-current-filter`,
  DefaultParameters
);
