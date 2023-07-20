import { atom } from 'jotai';

import { FederatedModule, FederatedWidgetProperties } from './models';

export const federatedModulesAtom = atom<Array<FederatedModule> | null>(null);

export const federatedWidgetsAtom = atom<Array<FederatedModule> | null>(null);

export const federatedWidgetsPropertiesAtom =
  atom<Array<FederatedWidgetProperties> | null>(null);
