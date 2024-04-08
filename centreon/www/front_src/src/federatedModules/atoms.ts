import { atom } from 'jotai';

import { FederatedWidgetProperties } from './models';

export const federatedWidgetsPropertiesAtom =
  atom<Array<FederatedWidgetProperties> | null>(null);
