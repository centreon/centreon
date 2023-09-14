import { useTranslation } from 'react-i18next';
import { find, propEq } from 'ramda';

import { Box, ListItemText } from '@mui/material';

import { SingleAutocompleteField } from '@centreon/ui';

import { labelWidgetLibrary } from '../../translatedLabels';
import { useAddWidgetStyles } from '../addWidget.styles';

import useWidgetSelection from './useWidgetSelection';
import { useWidgetSelectionStyles } from './widgetProperties.styles';

import { FederatedWidgetProperties } from 'www/front_src/src/federatedModules/models';
import { Avatar } from 'packages/ui/src/components';

const WidgetSelection = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWidgetSelectionStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();

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
    <Box className={classes.widgetSelection}>
      <Avatar compact className={avatarClasses.widgetAvatar}>
        1
      </Avatar>
      <SingleAutocompleteField
        className={classes.selectField}
        label={t(labelWidgetLibrary)}
        options={options}
        renderOption={renderOption}
        value={selectedWidget || null}
        onChange={(_, newValue) => selectWidget(newValue)}
        onTextChange={searchWidgets}
      />
    </Box>
  );
};

export default WidgetSelection;
