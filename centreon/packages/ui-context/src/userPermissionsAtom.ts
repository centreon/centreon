import { atom } from "jotai";
import type { UserPermissions } from ".";

const userPermissionsAtom = atom<UserPermissions | null>(null);

export default userPermissionsAtom;
