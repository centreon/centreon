import { atom } from 'jotai';

import { defaultAcl } from './defaults';

import { Acl } from '.';

const aclAtom = atom<Acl>(defaultAcl);

export default aclAtom;
