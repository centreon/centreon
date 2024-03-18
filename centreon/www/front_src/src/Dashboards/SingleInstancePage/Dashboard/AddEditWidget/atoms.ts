import { atom } from 'jotai';

import { FederatedWidgetProperties } from '../../../../federatedModules/models';

import { Widget } from './models';

export const widgetFormInitialDataAtom = atom<Widget | null>(null);

export const widgetPropertiesAtom = atom<FederatedWidgetProperties | undefined>(
  undefined
);

export const singleMetricSelectionAtom = atom<boolean | undefined>(undefined);

export const singleHostPerMetricAtom = atom<boolean | undefined>(undefined);

export const customBaseColorAtom = atom<boolean | undefined>(undefined);

export const metricsOnlyAtom = atom<boolean | undefined>(undefined);
