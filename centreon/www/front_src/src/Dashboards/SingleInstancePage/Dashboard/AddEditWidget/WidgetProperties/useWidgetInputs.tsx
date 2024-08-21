import { useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  propEq,
  find,
  path,
  equals,
  has,
  pluck,
  difference,
  isEmpty,
  reject,
  includes,
  type
} from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { useDeepCompare } from '@centreon/ui';
import {
  platformVersionsAtom,
  featureFlagsDerivedAtom
} from '@centreon/ui-context';

import { Widget, WidgetPropertyProps } from '../models';
import {
  FederatedWidgetOption,
  FederatedWidgetOptionType
} from '../../../../../federatedModules/models';
import {
  customBaseColorAtom,
  singleResourceSelectionAtom,
  singleMetricSelectionAtom,
  widgetPropertiesAtom
} from '../atoms';
import { federatedWidgetsPropertiesAtom } from '../../../../../federatedModules/atoms';

import {
  WidgetMetrics,
  WidgetRefreshInterval,
  WidgetResources,
  WidgetRichTextEditor,
  WidgetTextField,
  WidgetThreshold,
  WidgetValueFormat,
  WidgetTimePeriod,
  WidgetTopBottomSettings,
  WidgetRadio,
  WidgetCheckboxes,
  WidgetTiles,
  WidgetDisplayType,
  WidgetSwitch,
  WidgetSelect,
  WidgetButtonGroup,
  WidgetSlider,
  WidgetText,
  WidgetConnectedAutocomplete,
  WidgetTimezone,
  WidgetLocale,
  WidgetColorSelector,
  WidgetTimeFormat,
  WidgetDatePicker
} from './Inputs';

export interface WidgetPropertiesRenderer {
  Component: (props: WidgetPropertyProps) => JSX.Element;
  key: string;
  props: WidgetPropertyProps;
}

export const propertiesInputType = {
  [FederatedWidgetOptionType.textfield]: WidgetTextField,
  [FederatedWidgetOptionType.resources]: WidgetResources,
  [FederatedWidgetOptionType.metrics]: WidgetMetrics,
  [FederatedWidgetOptionType.richText]: WidgetRichTextEditor,
  [FederatedWidgetOptionType.refreshInterval]: WidgetRefreshInterval,
  [FederatedWidgetOptionType.threshold]: WidgetThreshold,
  [FederatedWidgetOptionType.valueFormat]: WidgetValueFormat,
  [FederatedWidgetOptionType.timePeriod]: WidgetTimePeriod,
  [FederatedWidgetOptionType.topBottomSettings]: WidgetTopBottomSettings,
  [FederatedWidgetOptionType.radio]: WidgetRadio,
  [FederatedWidgetOptionType.checkbox]: WidgetCheckboxes,
  [FederatedWidgetOptionType.tiles]: WidgetTiles,
  [FederatedWidgetOptionType.displayType]: WidgetDisplayType,
  [FederatedWidgetOptionType.switch]: WidgetSwitch,
  [FederatedWidgetOptionType.select]: WidgetSelect,
  [FederatedWidgetOptionType.buttonGroup]: WidgetButtonGroup,
  [FederatedWidgetOptionType.slider]: WidgetSlider,
  [FederatedWidgetOptionType.text]: WidgetText,
  [FederatedWidgetOptionType.connectedAutocomplete]:
    WidgetConnectedAutocomplete,
  [FederatedWidgetOptionType.timezone]: WidgetTimezone,
  [FederatedWidgetOptionType.locale]: WidgetLocale,
  [FederatedWidgetOptionType.color]: WidgetColorSelector,
  [FederatedWidgetOptionType.timeFormat]: WidgetTimeFormat,
  [FederatedWidgetOptionType.datePicker]: WidgetDatePicker
};

export const DefaultComponent = (): JSX.Element => (
  <div data-testid="unknown widget property" />
);

export const useWidgetInputs = (
  widgetKey: string
): Array<WidgetPropertiesRenderer> | null => {
  const { values, validateForm } = useFormikContext<Widget>();

  const widgetProperties = useAtomValue(widgetPropertiesAtom);
  const federatedWidgetsProperties = useAtomValue(
    federatedWidgetsPropertiesAtom
  );
  const { modules } = useAtomValue(platformVersionsAtom);
  const featureFlags = useAtomValue(featureFlagsDerivedAtom);
  const setSingleMetricSection = useSetAtom(singleMetricSelectionAtom);
  const setCustomBaseColor = useSetAtom(customBaseColorAtom);
  const setSingleResourceSelection = useSetAtom(singleResourceSelectionAtom);
  const setWidgetProperties = useSetAtom(widgetPropertiesAtom);

  const selectedWidget = find(
    propEq(values.moduleName, 'moduleName'),
    federatedWidgetsProperties || []
  );

  const selectedWidgetProperties: {
    [key: string]: FederatedWidgetOption;
  } | null = path(widgetKey.split('.'), selectedWidget) || null;

  const inputs = useMemo(
    () =>
      selectedWidgetProperties
        ? Object.entries(selectedWidgetProperties)
            .filter(([, value]) => {
              const hasModule = value.hasModule
                ? has(value.hasModule, modules)
                : true;

              if (!value.hiddenCondition) {
                return true;
              }

              const { target, method, when, matches } = value.hiddenCondition;

              if (equals(target, 'featureFlags')) {
                return (
                  hasModule &&
                  !equals(featureFlags?.[value.hiddenCondition.when], matches)
                );
              }

              if (equals(method, 'includes')) {
                const formValue = path(when.split('.'), values);
                const property = value.hiddenCondition?.property;
                const items = property ? pluck(property, formValue) : formValue;
                const areItemsString = equals(type(items), 'String');

                return (
                  hasModule &&
                  (isEmpty(reject(equals(''), items)) ||
                    (areItemsString
                      ? !includes(items, matches)
                      : !isEmpty(
                          difference(reject(equals(''), items), matches)
                        )))
                );
              }

              return (
                hasModule && !equals(path(when.split('.'), values), matches)
              );
            })
            .map(([key, value]) => {
              const Component =
                propertiesInputType[value.type] || DefaultComponent;

              return {
                Component,
                group: value.group,
                key,
                props: {
                  ...(value as unknown as Omit<
                    WidgetPropertyProps,
                    'propertyName' | 'propertyType'
                  >),
                  propertyName: key,
                  propertyType: widgetKey
                }
              };
            })
        : null,
    [selectedWidgetProperties, values]
  );

  useEffect(
    () => {
      validateForm();
    },
    useDeepCompare([widgetProperties])
  );

  useEffect(
    () => {
      if (!selectedWidget) {
        return;
      }

      setSingleMetricSection(selectedWidget.singleMetricSelection);
      setSingleResourceSelection(selectedWidget.singleResourceSelection);
      setCustomBaseColor(selectedWidget.customBaseColor);
    },
    useDeepCompare([selectedWidget])
  );

  useEffect(() => {
    setWidgetProperties(selectedWidget);
  }, []);

  return inputs;
};
