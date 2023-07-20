import { ChangeEvent, useState } from 'react';

import { equals, filter, find, isNil, map, propEq } from 'ramda';
import { useFormikContext } from 'formik';

import { SelectEntry } from '@centreon/ui';

import useFederatedWidgets from '../../../../federatedModules/useFederatedWidgets';
import {
  FederatedModule,
  FederatedWidgetProperties
} from '../../../../federatedModules/models';
import { Widget } from '../models';

interface UseWidgetSelectionState {
  options: Array<SelectEntry>;
  searchWidgets: (event: ChangeEvent<HTMLInputElement>) => void;
  selectWidget: (widget: SelectEntry | null) => void;
  selectedWidget: SelectEntry | undefined;
  widgets: Array<FederatedWidgetProperties>;
}

const useWidgetSelection = (): UseWidgetSelectionState => {
  const [search, setSearch] = useState('');

  const { federatedWidgetsProperties, federatedWidgets } =
    useFederatedWidgets();

  const { setValues, values } = useFormikContext<Widget>();

  const filteredWidgets = filter(
    ({ title }) => title.includes(search),
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
        id: null,
        moduleName: null,
        options: {},
        panelConfiguration: null
      });

      return;
    }

    const selectedWidget = find(
      propEq('moduleName', widget.id),
      federatedWidgets || []
    ) as FederatedModule;

    const selectedWidgetProperties = find(
      propEq('moduleName', widget.id),
      federatedWidgetsProperties || []
    ) as FederatedWidgetProperties;

    const options = Object.entries(selectedWidgetProperties.options).reduce(
      (acc, [key, value]) => ({
        ...acc,
        [key]: value.defaultValue
      }),
      {}
    );

    setValues({
      id: selectedWidget.moduleName,
      moduleName: selectedWidget.moduleName,
      options,
      panelConfiguration: selectedWidget.federatedComponentsConfiguration
    });
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
