import { atom } from "jotai";

import type { AdditionalResource } from "./types";

export const additionalResourcesAtom = atom<Array<AdditionalResource>>([]);
