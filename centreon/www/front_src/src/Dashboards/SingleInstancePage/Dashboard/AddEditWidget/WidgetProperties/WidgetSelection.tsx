import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import WidgetsIcon from '@mui/icons-material/Widgets';
import {
  Box,
  ListItemIcon,
  ListItemText,
  SvgIcon,
  useTheme
} from '@mui/material';

import { SingleAutocompleteField } from '@centreon/ui';
import { Avatar, CollapsibleItem } from '@centreon/ui/components';

import parse from 'html-react-parser';
import { always, cond, equals, find, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import type { FederatedWidgetProperties } from '../../../../../federatedModules/models';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import {
  labelGenericWidgets,
  labelMBIReportingWidgets,
  labelRealTimeWidgets,
  labelWidgetType
} from '../../translatedLabels';
import { useAddWidgetStyles } from '../addWidget.styles';
import { WidgetType } from '../models';
import useWidgetSelection from './useWidgetSelection';
import { useWidgetSelectionStyles } from './widgetProperties.styles';

const WidgetSelection = (): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();
  const { classes } = useWidgetSelectionStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();

  const { options, widgets, searchWidgets, selectWidget, selectedWidget } =
    useWidgetSelection();

  const { canEditField } = useCanEditProperties();

  const getWidgetGroupTitle = cond([
    [equals(WidgetType.Generic), always(labelGenericWidgets)],
    [equals(WidgetType.RealTime), always(labelRealTimeWidgets)],
    [equals(WidgetType.MBI), always(labelMBIReportingWidgets)]
  ]);

  const renderGroup = ({ group, key, ...rest }): JSX.Element => (
    <CollapsibleItem
      classes={{ root: classes.groupContainer }}
      dataTestId={group}
      defaultExpanded
      expandIcon={<ExpandMoreIcon htmlColor={theme.palette.common.white} />}
      key={key}
      title={t(getWidgetGroupTitle(group))}
      titleProps={{ color: theme.palette.common.white, variant: 'body1' }}
    >
      {rest?.children}
    </CollapsibleItem>
  );

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
      <Avatar className={avatarClasses.widgetAvatar} compact>
        1
      </Avatar>
      <SingleAutocompleteField
        className={classes.selectField}
        disabled={!canEditField}
        groupBy={(option) => option.widgetType}
        label={t(labelWidgetType)}
        onChange={(_, newValue) => selectWidget(newValue)}
        onTextChange={searchWidgets}
        options={options}
        renderGroup={renderGroup}
        renderOption={renderOption}
        value={selectedWidget || null}
      />
    </Box>
  );
};

export default WidgetSelection;
