import { useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { propEq, find } from 'ramda';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import {
  ConditionalOptions,
  ShowInput,
  Widget,
  WidgetPropertyProps
} from '../models';
import { FederatedWidgetOptionType } from '../../../../federatedModules/models';
import {
  customBaseColorAtom,
  singleHostPerMetricAtom,
  singleMetricSelectionAtom,
  widgetPropertiesAtom
} from '../atoms';

import {
  WidgetMetrics,
  WidgetRefreshInterval,
  WidgetResources,
  WidgetRichTextEditor,
  WidgetSingleMetricGraphType,
  WidgetTextField,
  WidgetThreshold,
  WidgetValueFormat,
  WidgetTimePeriod,
  WidgetTopBottomSettings,
  WidgetRadio,
  WidgetCheckboxes,
  WidgetTiles
} from './Inputs';

import { useDeepCompare } from 'packages/ui/src';
import { federatedWidgetsPropertiesAtom } from 'www/front_src/src/federatedModules/atoms';

export interface WidgetPropertiesRenderer {
  Component: (props: WidgetPropertyProps) => JSX.Element;
  key: string;
  props: {
    defaultValue: unknown | ConditionalOptions<unknown>;
    label: string;
    propertyName: string;
    propertyType: string;
    required?: boolean;
    show?: ShowInput;
    type: FederatedWidgetOptionType;
  };
}

export const propertiesInputType = {
  [FederatedWidgetOptionType.textfield]: WidgetTextField,
  [FederatedWidgetOptionType.resources]: WidgetResources,
  [FederatedWidgetOptionType.metrics]: WidgetMetrics,
  [FederatedWidgetOptionType.richText]: WidgetRichTextEditor,
  [FederatedWidgetOptionType.refreshInterval]: WidgetRefreshInterval,
  [FederatedWidgetOptionType.threshold]: WidgetThreshold,
  [FederatedWidgetOptionType.singleMetricGraphType]:
    WidgetSingleMetricGraphType,
  [FederatedWidgetOptionType.valueFormat]: WidgetValueFormat,
  [FederatedWidgetOptionType.timePeriod]: WidgetTimePeriod,
  [FederatedWidgetOptionType.topBottomSettings]: WidgetTopBottomSettings,
  [FederatedWidgetOptionType.radio]: WidgetRadio,
  [FederatedWidgetOptionType.checkbox]: WidgetCheckboxes,
  [FederatedWidgetOptionType.tiles]: WidgetTiles
};

const DefaultComponent = (): JSX.Element => <div />;

export const useWidgetInputs = (
  widgetKey: string
): Array<WidgetPropertiesRenderer> | null => {
  const { values, validateForm } = useFormikContext<Widget>();

  const [widgetProperties, setWidgetProperties] = useAtom(widgetPropertiesAtom);
  const federatedWidgetsProperties = useAtomValue(
    federatedWidgetsPropertiesAtom
  );
  const setSingleMetricSection = useSetAtom(singleMetricSelectionAtom);
  const setCustomBaseColor = useSetAtom(customBaseColorAtom);
  const setSingleHostPerMetric = useSetAtom(singleHostPerMetricAtom);

  const selectedWidget = find(
    propEq(values.moduleName, 'moduleName'),
    federatedWidgetsProperties || []
  );

  const selectedWidgetProperties = selectedWidget?.[widgetKey] || null;

  const inputs = useMemo(
    () =>
      selectedWidgetProperties
        ? Object.entries(selectedWidgetProperties).map(([key, value]) => {
            const Component =
              propertiesInputType[value.type] || DefaultComponent;

            return {
              Component,
              key,
              props: {
                defaultValue: value.defaultValue,
                label: value.label,
                options: value.options,
                propertyName: key,
                propertyType: widgetKey,
                required: value.required,
                secondaryLabel: value.secondaryLabel,
                show: value.show,
                type: value.type
              }
            };
          })
        : null,
    [selectedWidgetProperties]
  );

  useEffect(
    () => {
      setWidgetProperties(inputs);
    },
    useDeepCompare([inputs])
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

  return inputs;
};
