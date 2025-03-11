import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { NamedEntity } from '../../models';

import { baseKey } from '../../storage';
import { DefaultParameters, TokenFilter } from './models';

export const currentFilterAtom = atomWithStorage<TokenFilter>(
  `${baseKey}tokens-current-filter`,
  DefaultParameters
);

export const usersAtom = atom<Array<NamedEntity>>([]);
export const creatorsAtom = atom<Array<NamedEntity>>([]);
export const expirationDateAtom = atom<Date | null>(null);
export const creationDateAtom = atom<Date | null>(null);
export const isRevokedAtom = atom<boolean | null>(null);
