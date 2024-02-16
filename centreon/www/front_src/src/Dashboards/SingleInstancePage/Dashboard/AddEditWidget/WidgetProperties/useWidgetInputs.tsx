import { useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { propEq, find } from 'ramda';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import { useDeepCompare } from '@centreon/ui';

import {
  ConditionalOptions,
  ShowInput,
  Widget,
  WidgetPropertyProps
} from '../models';
import { FederatedWidgetOptionType } from '../../../../../federatedModules/models';
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
  DisplayType
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
  [FederatedWidgetOptionType.displayType]: DisplayType
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
                ...(value as WidgetPropertyProps),
                propertyName: key,
                propertyType: widgetKey
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
