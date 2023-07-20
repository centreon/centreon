import { ChangeEvent, useState } from 'react';

import { filter, find, isNil, map, propEq } from 'ramda';
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
  widgets: Array<FederatedWidgetProperties>;
}

const useWidgetSelection = (): UseWidgetSelectionState => {
  const [search, setSearch] = useState('');

  const { federatedWidgetsProperties, federatedWidgets } =
    useFederatedWidgets();

  const { setValues } = useFormikContext<Widget>();

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
      options,
      panelConfiguration: selectedWidget.federatedComponentsConfiguration
    });
  };

  return {
    options: formattedWidgets,
    searchWidgets,
    selectWidget,
    widgets: filteredWidgets
  };
};

export default useWidgetSelection;
