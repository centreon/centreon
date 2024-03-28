import { useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { propEq, find, path, equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { useDeepCompare } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../models';
import {
  FederatedWidgetOption,
  FederatedWidgetOptionType
} from '../../../../../federatedModules/models';
import {
  customBaseColorAtom,
  singleHostPerMetricAtom,
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
  WidgetSwitch
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
  [FederatedWidgetOptionType.switch]: WidgetSwitch
};

const DefaultComponent = (): JSX.Element => (
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
  const setSingleMetricSection = useSetAtom(singleMetricSelectionAtom);
  const setCustomBaseColor = useSetAtom(customBaseColorAtom);
  const setSingleHostPerMetric = useSetAtom(singleHostPerMetricAtom);
  const setWidgetProperties = useSetAtom(widgetPropertiesAtom);

  const selectedWidget = find(
    propEq(values.moduleName, 'moduleName'),
    federatedWidgetsProperties || []
  );

  const selectedWidgetProperties: {
    [key: string]: FederatedWidgetOption;
  } | null = selectedWidget?.[widgetKey] || null;

  const inputs = useMemo(
    () =>
      selectedWidgetProperties
        ? Object.entries(selectedWidgetProperties)
            .filter(([, value]) => {
              if (!value.hiddenCondition) {
                return true;
              }

              return !equals(
                path(value.hiddenCondition.when.split('.'), values),
                value.hiddenCondition.matches
              );
            })
            .map(([key, value]) => {
              const Component =
                propertiesInputType[value.type] || DefaultComponent;

              return {
                Component,
                key,
                props: {
                  ...(value as Omit<
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
      setSingleHostPerMetric(selectedWidget.singleHostPerMetric);
      setCustomBaseColor(selectedWidget.customBaseColor);
    },
    useDeepCompare([selectedWidget])
  );

  useEffect(() => {
    setWidgetProperties(selectedWidget);
  }, []);

  return inputs;
};
