import { atom } from 'jotai';
import { isNil, pick } from 'ramda';

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

export const widgetPropertiesMetaPropertiesDerivedAtom = atom<Pick<
  FederatedWidgetProperties,
  | 'onlyResourcesWithPerformanceData'
  | 'singleHostPerMetric'
  | 'customBaseColor'
  | 'singleMetricSelection'
> | null>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return null;
  }

  return pick(
    [
      'singleHostPerMetric',
      'singleHostPerMetric',
      'customBaseColor',
      'onlyResourcesWithPerformanceData'
    ],
    widgetProperties
  );
});
