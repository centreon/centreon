import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { propEq, find } from 'ramda';

import { Widget, WidgetPropertyProps } from '../models';
import useFederatedWidgets from '../../../../federatedModules/useFederatedWidgets';
import { FederatedWidgetOptionType } from '../../../../federatedModules/models';

import { WidgetTextField } from './Inputs';

interface WidgetPropertiesRenderer {
  Component: (props: WidgetPropertyProps) => JSX.Element;
  key: string;
  props: {
    label: string;
    propertyName: string;
  };
}

export const propertiesInputType = {
  [FederatedWidgetOptionType.textfield]: WidgetTextField
};

export const useWidgetProperties = (): Array<WidgetPropertiesRenderer> => {
  const { values } = useFormikContext<Widget>();

  const { federatedWidgetsProperties } = useFederatedWidgets();

  const widgetProperties =
    find(
      propEq('moduleName', values.moduleName),
      federatedWidgetsProperties || []
    )?.options || {};

  const inputs = useMemo(
    () =>
      Object.entries(widgetProperties).map(([key, value]) => {
        const Component = propertiesInputType[value.type];

        return {
          Component,
          key,
          props: {
            label: 'Text',
            propertyName: key
          }
        };
      }),
    [widgetProperties]
  );

  return inputs;
};
