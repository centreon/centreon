import { atom } from 'jotai';
import { concat, equals, isNil, values } from 'ramda';

import {
  FederatedWidgetOptionType,
  FederatedWidgetProperties
} from '../../../../federatedModules/models';

import { Widget } from './models';

export const widgetFormInitialDataAtom = atom<Widget | null>(null);

export const widgetPropertiesAtom = atom<FederatedWidgetProperties | undefined>(
  undefined
);

export const singleMetricSelectionAtom = atom<boolean | undefined>(undefined);

export const singleResourceSelectionAtom = atom<boolean | undefined>(undefined);

export const customBaseColorAtom = atom<boolean | undefined>(undefined);

export const metricsOnlyAtom = atom<boolean | undefined>(undefined);

export const hasMetricInputTypeDerivedAtom = atom<boolean>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return false;
  }

  const properties = concat(
    values(widgetProperties.data),
    values(widgetProperties.options)
  );

  return properties.some(({ type }) =>
    equals(type, FederatedWidgetOptionType.metrics)
  );
});

export const metricInputKeyDerivedAtom = atom<string | undefined>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return undefined;
  }

  const properties = concat(
    Object.entries(widgetProperties.data || {}),
    Object.entries(widgetProperties.options || {})
  );

  const metricInput = properties.find(([, { type }]) =>
    equals(type, FederatedWidgetOptionType.metrics)
  );

  return metricInput?.[0];
});

export const resourcesInputKeyDerivedAtom = atom<string | undefined>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return undefined;
  }

  const properties = concat(
    Object.entries(widgetProperties.data || {}),
    Object.entries(widgetProperties.options || {})
  );

  const resourcesInput = properties.find(([, { type }]) =>
    equals(type, FederatedWidgetOptionType.resources)
  );

  return resourcesInput?.[0];
});

export const localeInputKeyDerivedAtom = atom<string | undefined>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return undefined;
  }

  const properties = concat(
    Object.entries(widgetProperties.data || {}),
    Object.entries(
      (widgetProperties.options.groups
        ? widgetProperties.options.elements
        : widgetProperties.options) || {}
    )
  );

  const metricInput = properties.find(([, { type }]) =>
    equals(type, FederatedWidgetOptionType.locale)
  );

  return metricInput?.[0];
});

export const timezoneInputKeyDerivedAtom = atom<string | undefined>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return undefined;
  }

  const properties = concat(
    Object.entries(widgetProperties.data || {}),
    Object.entries(
      (widgetProperties.options.groups
        ? widgetProperties.options.elements
        : widgetProperties.options) || {}
    )
  );

  const metricInput = properties.find(([, { type }]) =>
    equals(type, FederatedWidgetOptionType.timezone)
  );

  return metricInput?.[0];
});

export const widgetPropertiesMetaPropertiesDerivedAtom = atom<Pick<
  FederatedWidgetProperties,
  'singleResourceSelection' | 'customBaseColor' | 'singleMetricSelection'
> | null>((get) => {
  const widgetProperties = get(widgetPropertiesAtom);

  if (isNil(widgetProperties)) {
    return null;
  }

  return {
    customBaseColor: widgetProperties.customBaseColor,
    singleMetricSelection: widgetProperties.singleMetricSelection,
    singleResourceSelection: widgetProperties.singleResourceSelection
  };
});
