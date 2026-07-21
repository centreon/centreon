import { atom } from 'jotai';

import type { User } from '.';
import { defaultUser } from './defaults';

const userAtom = atom<User>(defaultUser);

export default userAtom;
