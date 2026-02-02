import { atom } from "jotai";
import type { Acl } from ".";
import { defaultAcl } from "./defaults";

const aclAtom = atom<Acl>(defaultAcl);

export default aclAtom;
