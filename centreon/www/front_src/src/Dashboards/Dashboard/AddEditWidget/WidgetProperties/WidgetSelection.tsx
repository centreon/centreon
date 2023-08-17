import { useTranslation } from 'react-i18next';
import { find, propEq } from 'ramda';

import { ListItemText } from '@mui/material';

import { SingleAutocompleteField } from '@centreon/ui';

import { labelWidgetLibrary } from '../../translatedLabels';

import useWidgetSelection from './useWidgetSelection';

import { FederatedWidgetProperties } from 'www/front_src/src/federatedModules/models';

const WidgetSelection = (): JSX.Element => {
  const { t } = useTranslation();
  const { options, widgets, searchWidgets, selectWidget, selectedWidget } =
    useWidgetSelection();

  const renderOption = (renderProps, option): JSX.Element => {
    const widget = find(
      propEq('title', option.name),
      widgets
    ) as FederatedWidgetProperties;

    return (
      <li {...renderProps}>
        <ListItemText primary={widget.title} secondary={widget.description} />
      </li>
    );
  };

  return (
    <SingleAutocompleteField
      label={t(labelWidgetLibrary)}
      options={options}
      renderOption={renderOption}
      value={selectedWidget || null}
      onChange={(_, newValue) => selectWidget(newValue)}
      onTextChange={searchWidgets}
    />
  );
};

export default WidgetSelection;
