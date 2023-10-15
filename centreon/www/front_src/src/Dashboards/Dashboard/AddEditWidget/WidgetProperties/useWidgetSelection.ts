import { ChangeEvent, useState } from 'react';

import { equals, filter, find, isNil, map, propEq } from 'ramda';
import { useFormikContext } from 'formik';
import { useAtomValue, useSetAtom } from 'jotai';

import { SelectEntry } from '@centreon/ui';

import {
  FederatedModule,
  FederatedWidgetProperties
} from '../../../../federatedModules/models';
import { Widget } from '../models';
import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../../../federatedModules/atoms';
import {
  customBaseColorAtom,
  singleMetricSelectionAtom,
  singleResourceTypeSelectionAtom
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
  const setSingleResourceTypeSelection = useSetAtom(
    singleResourceTypeSelectionAtom
  );
  const setCustomBaseColor = useSetAtom(customBaseColorAtom);

  const { setValues, values } = useFormikContext<Widget>();

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
          },
          openLinksInNewTab: true
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

    const options = Object.entries(selectedWidgetProperties.options).reduce(
      (acc, [key, value]) => ({
        ...acc,
        [key]: value.defaultValue
      }),
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
      !isGenericText(selectedWidget.federatedComponentsConfiguration.path);

    setSingleMetricSection(selectedWidgetProperties.singleMetricSelection);
    setSingleResourceTypeSelection(
      selectedWidgetProperties.singleResourceTypeSelection
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
        name: currentValues.options.name,
        openLinksInNewTab: currentValues.options.openLinksInNewTab || true
      },
      panelConfiguration: selectedWidget.federatedComponentsConfiguration
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
