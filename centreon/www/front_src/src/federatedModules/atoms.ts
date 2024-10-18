import { atom } from 'jotai';

import { pluck } from 'ramda';
import { internalWidgetComponents } from '../Dashboards/SingleInstancePage/Dashboard/Widgets/widgets';
import { FederatedWidgetProperties } from './models';

export const federatedWidgetsPropertiesAtom = atom<
  Array<FederatedWidgetProperties>
>(pluck('properties', internalWidgetComponents));
