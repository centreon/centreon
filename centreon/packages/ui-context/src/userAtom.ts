import { atom } from 'jotai';

import { defaultUser } from './defaults';

import { User } from '.';

const userAtom = atom<User>(defaultUser);

export default userAtom;
