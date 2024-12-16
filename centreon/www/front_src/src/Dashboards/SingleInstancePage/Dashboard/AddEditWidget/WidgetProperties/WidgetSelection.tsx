import parse from 'html-react-parser';
import { find, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import WidgetsIcon from '@mui/icons-material/Widgets';
import { Box, ListItemIcon, ListItemText, SvgIcon } from '@mui/material';

import { SingleAutocompleteField } from '@centreon/ui';
import { Avatar } from '@centreon/ui/components';

import type { FederatedWidgetProperties } from '../../../../../federatedModules/models';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import { labelWidgetType } from '../../translatedLabels';
import { useAddWidgetStyles } from '../addWidget.styles';

import useWidgetSelection from './useWidgetSelection';
import { useWidgetSelectionStyles } from './widgetProperties.styles';

const WidgetSelection = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWidgetSelectionStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();

  const { options, widgets, searchWidgets, selectWidget, selectedWidget } =
    useWidgetSelection();

  const { canEditField } = useCanEditProperties();

  const renderOption = (renderProps, option): JSX.Element => {
    const widget = find(
      propEq(option.name, 'title'),
      widgets
    ) as FederatedWidgetProperties;

    return (
      <li {...renderProps}>
        <ListItemIcon>
          {widget.icon ? (
            <SvgIcon
              className={classes.widgetIcon}
              color="inherit"
              data-icon={widget.title}
              viewBox="0 0 60 60"
            >
              {parse(widget.icon)}
            </SvgIcon>
          ) : (
            <WidgetsIcon
              className={classes.widgetIcon}
              data-icon={`default-${widget.title}`}
            />
          )}
        </ListItemIcon>
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
