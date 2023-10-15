import { useTranslation } from 'react-i18next';
import { find, propEq } from 'ramda';

import { Box, ListItemText } from '@mui/material';

import { SingleAutocompleteField } from '@centreon/ui';

import { labelWidgetType } from '../../translatedLabels';
import { useAddWidgetStyles } from '../addWidget.styles';
import { editProperties } from '../../hooks/useCanEditDashboard';

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

  const { canEditField } = editProperties.useCanEditProperties();

  const renderOption = (renderProps, option): JSX.Element => {
    const widget = find(
      propEq(option.name, 'title'),
      widgets
    ) as FederatedWidgetProperties;

    return (
      <li {...renderProps}>
        <ListItemText
          primary={t(widget.title)}
          secondary={t(widget.description)}
        />
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
        disabled={!canEditField}
        label={t(labelWidgetType)}
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
