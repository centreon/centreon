import { atom } from 'jotai';

import { CheckActionAtom } from '../../../Actions/Resource/Check/checkAtoms';

export const checkActionDetailsAtom = atom<CheckActionAtom | null>(null);
