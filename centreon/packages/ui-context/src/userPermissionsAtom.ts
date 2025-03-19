import { atom } from 'jotai';

import { UserPermissions } from '.';

const userPermissionsAtom = atom<UserPermissions | null>(null);

export default userPermissionsAtom;
