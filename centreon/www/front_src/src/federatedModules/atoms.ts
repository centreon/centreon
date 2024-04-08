import { atom } from 'jotai';

import { FederatedModule, FederatedWidgetProperties } from './models';

export const federatedWidgetsPropertiesAtom =
  atom<Array<FederatedWidgetProperties> | null>(null);
