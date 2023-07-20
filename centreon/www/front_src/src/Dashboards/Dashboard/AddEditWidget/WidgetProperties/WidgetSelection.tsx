import { useTranslation } from 'react-i18next';
import { find, propEq } from 'ramda';

import { ListItemText, Typography } from '@mui/material';

import { SingleAutocompleteField } from '@centreon/ui';

import { labelWidgetsLibrary } from '../../translatedLabels';

import useWidgetSelection from './useWidgetSelection';

import { FederatedWidgetProperties } from 'www/front_src/src/federatedModules/models';

const WidgetSelection = (): JSX.Element => {
  const { t } = useTranslation();
  const { options, widgets, searchWidgets, selectWidget } =
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
      label={t(labelWidgetsLibrary)}
      options={options}
      renderOption={renderOption}
      onChange={(_, newValue) => selectWidget(newValue)}
      onTextChange={searchWidgets}
    />
  );
};

export default WidgetSelection;
