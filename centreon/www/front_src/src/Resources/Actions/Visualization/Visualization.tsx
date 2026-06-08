import { Typography } from '@mui/material';
import Grid from '@mui/material/Grid';

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

const actions = [
  {
    IconOnActive: viewByAllActive,
    IconOnActiveDark: viewByAllActiveDark,
    IconOnInactive: viewByAllInactive,
    IconOnInactiveDark: viewByAllInactiveDark,
    title: labelAll,
    type: Visualization.All
  },
  {
    IconOnActive: viewByHostActive,
    IconOnActiveDark: viewByHostActiveDark,
    IconOnInactive: viewByHostInactive,
    IconOnInactiveDark: viewByHostInactiveDark,
    title: labelViewByHost,
    type: Visualization.Host
  },
  {
    IconOnActive: viewByServiceActive,
    IconOnActiveDark: viewByServiceActiveDark,
    IconOnInactive: viewByServiceInactive,
    IconOnInactiveDark: viewByServiceInactiveDark,
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
      {actions.map(
        ({
          type,
          title,
          IconOnActive,
          IconOnActiveDark,
          IconOnInactive,
          IconOnInactiveDark
        }) => {
          return (
            <Action
              IconOnActive={IconOnActive}
              IconOnActiveDark={IconOnActiveDark}
              IconOnInactive={IconOnInactive}
              IconOnInactiveDark={IconOnInactiveDark}
              key={title}
              title={title}
              type={type}
            />
          );
        }
      )}
    </Grid>
  );
};

export default VisualizationActions;
