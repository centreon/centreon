import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { baseKey } from '../../../storage';
import { PersonalInformation } from '../../models';

import { DefaultParameters, TokenFilter, User } from './models';

export const currentFilterAtom = atomWithStorage<TokenFilter>(
  `${baseKey}tokens-current-filter`,
  DefaultParameters
);

export const usersAtom = atom<Array<PersonalInformation>>([]);
export const creatorsAtom = atom<Array<PersonalInformation>>([]);
