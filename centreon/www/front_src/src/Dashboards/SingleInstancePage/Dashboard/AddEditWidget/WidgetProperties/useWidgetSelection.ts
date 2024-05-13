import { ChangeEvent, useState } from 'react';

import { equals, filter, find, has, isNil, map, propEq, reduce } from 'ramda';
import { useFormikContext } from 'formik';
import { useAtomValue, useSetAtom } from 'jotai';

import { SelectEntry } from '@centreon/ui';
import { federatedWidgetsAtom } from '@centreon/ui-context';

import {
  FederatedModule,
  FederatedWidgetProperties
} from '../../../../../federatedModules/models';
import { Widget } from '../models';
import { federatedWidgetsPropertiesAtom } from '../../../../../federatedModules/atoms';
import {
  customBaseColorAtom,
  singleResourceSelectionAtom,
  singleMetricSelectionAtom,
  widgetPropertiesAtom
} from '../atoms';
import { isGenericText } from '../../utils';

interface UseWidgetSelectionState {
  options: Array<SelectEntry>;
  searchWidgets: (event: ChangeEvent<HTMLInputElement>) => void;
  selectWidget: (widget: SelectEntry | null) => void;
  selectedWidget: SelectEntry | undefined;
  widgets: Array<FederatedWidgetProperties>;
}

const useWidgetSelection = (): UseWidgetSelectionState => {
  const [search, setSearch] = useState('');

  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const federatedWidgetsProperties = useAtomValue(
    federatedWidgetsPropertiesAtom
  );
  const setSingleMetricSection = useSetAtom(singleMetricSelectionAtom);
  const setSingleResourceSelection = useSetAtom(singleResourceSelectionAtom);
  const setCustomBaseColor = useSetAtom(customBaseColorAtom);
  const setWidgetProperties = useSetAtom(widgetPropertiesAtom);

  const { setValues, values, setTouched } = useFormikContext<Widget>();

  const filteredWidgets = filter(
    ({ title }) => title?.includes(search),
    federatedWidgetsProperties || []
  );

  const formattedWidgets = map(
    ({ title, moduleName }) => ({
      id: moduleName,
      name: title
    }),
    filteredWidgets
  );

  const searchWidgets = (event: ChangeEvent<HTMLInputElement>): void => {
    setSearch(event.target.value);
  };

  const selectWidget = (widget: SelectEntry | null): void => {
    if (isNil(widget)) {
      setValues({
        data: null,
        id: null,
        moduleName: null,
        options: {
          description: {
            content: null,
            enabled: true
          }
        },
        panelConfiguration: null
      });

      return;
    }

    const selectedWidget = find(
      propEq(widget.id, 'moduleName'),
      federatedWidgets || []
    ) as FederatedModule;

    const selectedWidgetProperties = find(
      propEq(widget.id, 'moduleName'),
      federatedWidgetsProperties || []
    ) as FederatedWidgetProperties;

    setWidgetProperties(selectedWidgetProperties);

    setTouched(
      reduce(
        (acc, key) => ({
          ...acc,
          [key]: false
        }),
        {},
        Object.keys(selectedWidgetProperties)
      ),
      false
    );

    const options = Object.entries(selectedWidgetProperties.options).reduce(
      (acc, [key, value]) => {
        if (!has('when', value.defaultValue)) {
          return {
            ...acc,
            [key]: value.defaultValue
          };
        }

        return {
          ...acc,
          [key]: equals(
            selectedWidgetProperties.options[value.defaultValue.when]
              .defaultValue,
            value.defaultValue.is
          )
            ? value.defaultValue.then
            : value.defaultValue.otherwise
        };
      },
      {}
    );

    const data = Object.entries(selectedWidgetProperties.data || {}).reduce(
      (acc, [key, value]) => ({
        ...acc,
        [key]: value.defaultValue
      }),
      {}
    );
    const shouldResetDescription =
      equals(values.moduleName, 'centreon-widget-generictext') &&
      !isGenericText(selectedWidget.federatedComponentsConfiguration[0].path);

    setSingleMetricSection(selectedWidgetProperties.singleMetricSelection);
    setSingleResourceSelection(
      selectedWidgetProperties.singleResourceSelection
    );
    setCustomBaseColor(selectedWidgetProperties.customBaseColor);

    setValues((currentValues) => ({
      data,
      id: selectedWidget.moduleName,
      moduleName: selectedWidget.moduleName,
      options: {
        ...options,
        description:
          shouldResetDescription || isNil(currentValues.options.description)
            ? {
                content: null,
                enabled: true
              }
            : currentValues.options.description,
        name: currentValues.options.name
      },
      panelConfiguration: selectedWidget.federatedComponentsConfiguration[0]
    }));
  };

  const selectedWidget = formattedWidgets.find(({ id }) =>
    equals(values.moduleName, id)
  );

  return {
    options: formattedWidgets,
    searchWidgets,
    selectWidget,
    selectedWidget,
    widgets: filteredWidgets
  };
};

export default useWidgetSelection;
