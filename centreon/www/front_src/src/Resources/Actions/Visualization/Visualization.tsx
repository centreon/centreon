import { useTranslation } from 'react-i18next';

import { Grid, Typography } from '@mui/material';

import viewByAllActive from '../../icons/view_all_actif.svg';
import viewByAllInactive from '../../icons/view_all_inactif.svg';
import viewByHostActive from '../../icons/view_host_actif.svg';
import viewByHostInactive from '../../icons/view_host_inactif.svg';
import viewByServiceActive from '../../icons/view_service_actif.svg';
import viewByServiceInactive from '../../icons/view_service_inactif.svg';
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
      container
      className={classes.visualizationContainer}
      data-testid="tree view"
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
