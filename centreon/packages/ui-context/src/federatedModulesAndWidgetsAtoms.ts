import { atom } from "jotai";

import type { FederatedModule } from "./types";

export const federatedModulesAtom = atom<Array<FederatedModule> | null>(null);

export const federatedWidgetsAtom = atom<Array<FederatedModule> | null>(null);
