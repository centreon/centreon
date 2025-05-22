import { useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useAtomValue, useSetAtom } from 'jotai';
import { path, find, propEq } from 'ramda';

import { useDeepCompare } from '@centreon/ui';
import {
  featureFlagsDerivedAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { federatedWidgetsPropertiesAtom } from '../../../../../federatedModules/atoms';
import {
  FederatedWidgetOption,
  FederatedWidgetOptionType
} from '../../../../../federatedModules/models';
import {
  customBaseColorAtom,
  singleResourceSelectionAtom,
  widgetPropertiesAtom
} from '../atoms';
import { Widget, WidgetPropertyProps } from '../models';

import {
  WidgetBoundaries,
  WidgetButtonGroup,
  WidgetCheckboxes,
  WidgetColorSelector,
  WidgetConnectedAutocomplete,
  WidgetDatePicker,
  WidgetDisplayType,
  WidgetLocale,
  WidgetMetrics,
  WidgetRadio,
  WidgetRefreshInterval,
  WidgetResources,
  WidgetRichTextEditor,
  WidgetSelect,
  WidgetSlider,
  WidgetSwitch,
  WidgetText,
  WidgetTextField,
  WidgetThreshold,
  WidgetTiles,
  WidgetTimeFormat,
  WidgetTimePeriod,
  WidgetTimezone,
  WidgetTopBottomSettings,
  WidgetValueFormat,
  WidgetWarning
} from './Inputs';
import { handleHiddenConditions } from './handleHiddenConditions';
import WidgetSnack from './Inputs/SnackBar';

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
  [FederatedWidgetOptionType.datePicker]: WidgetDatePicker,
  [FederatedWidgetOptionType.warning]: WidgetWarning,
  [FederatedWidgetOptionType.boundaries]: WidgetBoundaries,
  [FederatedWidgetOptionType.snackBar]: WidgetSnack,

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
        ? handleHiddenConditions({
            modules,
            featureFlags,
            widgetProperties: selectedWidgetProperties,
            values
          }).map(([key, value]) => {
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
