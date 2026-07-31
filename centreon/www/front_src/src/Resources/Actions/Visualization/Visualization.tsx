import { Typography } from '@mui/material';
import Grid from '@mui/material/Grid';
import { useTheme } from '@mui/material/styles';

import { ThemeMode } from '@centreon/ui-context';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import viewByAllActive from '../../icons/view_all_actif.svg';
import viewByAllActiveDark from '../../icons/view_all_actif_dark.svg';
import viewByAllInactive from '../../icons/view_all_inactif.svg';
import viewByAllInactiveDark from '../../icons/view_all_inactif_dark.svg';
import viewByHostActive from '../../icons/view_host_actif.svg';
import viewByHostActiveDark from '../../icons/view_host_actif_dark.svg';
import viewByHostInactive from '../../icons/view_host_inactif.svg';
import viewByHostInactiveDark from '../../icons/view_host_inactif_dark.svg';
import viewByServiceActive from '../../icons/view_service_actif.svg';
import viewByServiceActiveDark from '../../icons/view_service_actif_dark.svg';
import viewByServiceInactive from '../../icons/view_service_inactif.svg';
import viewByServiceInactiveDark from '../../icons/view_service_inactif_dark.svg';
import { Visualization } from '../../models';
import {
  labelAll,
  labelDisplayView,
  labelViewByHost,
  labelViewByService
} from '../../translatedLabels';
import Action from './Action';
import { useStyles } from './Visualization.styles';

const lightActions = [
  {
    IconOnActive: viewByAllActive,
    IconOnInactive: viewByAllInactive,
    title: labelAll,
    type: Visualization.All
  },
  {
    IconOnActive: viewByHostActive,
    IconOnInactive: viewByHostInactive,
    title: labelViewByHost,
    type: Visualization.Host
  },
  {
    IconOnActive: viewByServiceActive,
    IconOnInactive: viewByServiceInactive,
    title: labelViewByService,
    type: Visualization.Service
  }
];

const darkActions = [
  {
    IconOnActive: viewByAllActiveDark,
    IconOnInactive: viewByAllInactiveDark,
    title: labelAll,
    type: Visualization.All
  },
  {
    IconOnActive: viewByHostActiveDark,
    IconOnInactive: viewByHostInactiveDark,
    title: labelViewByHost,
    type: Visualization.Host
  },
  {
    IconOnActive: viewByServiceActiveDark,
    IconOnInactive: viewByServiceInactiveDark,
    title: labelViewByService,
    type: Visualization.Service
  }
];

interface Props {
  displayCondensed?: boolean;
}

const VisualizationActions = ({
  displayCondensed = false
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const actions = equals(theme.palette.mode, ThemeMode.dark)
    ? darkActions
    : lightActions;

  return (
    <Grid
      className={classes.visualizationContainer}
      container
      data-testid="tree view"
      size={12}
    >
      {!displayCondensed && (
        <Typography className={classes.text} variant="body1">
          {t(labelDisplayView)}
        </Typography>
      )}
      {actions.map(({ type, title, IconOnActive, IconOnInactive }) => {
        return (
          <Action
            IconOnActive={IconOnActive}
            IconOnInactive={IconOnInactive}
            key={title}
            title={title}
            type={type}
          />
        );
      })}
    </Grid>
  );
};

export default VisualizationActions;
