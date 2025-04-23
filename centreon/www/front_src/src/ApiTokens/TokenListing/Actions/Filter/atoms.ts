import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../../../storage';
import { PersonalInformation } from '../../models';

import { DefaultParameters, TokenFilter } from './models';

export const currentFilterAtom = atomWithStorage<TokenFilter>(
  `${baseKey}tokens-current-filter`,
  DefaultParameters
);

export const usersAtom = atom<Array<PersonalInformation>>([]);
export const creatorsAtom = atom<Array<PersonalInformation>>([]);
export const expirationDateAtom = atom<Date | null>(null);
export const creationDateAtom = atom<Date | null>(null);
export const isRevokedAtom = atom<boolean | null>(null);
