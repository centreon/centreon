import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import viewByServiceActive from '../../icons/view_service_actif.svg';
import viewByServiceInactive from '../../icons/view_service_inactif.svg';
import viewByHostActive from '../../icons/view_host_actif.svg';
import viewByHostInactive from '../../icons/view_host_inactif.svg';
import viewByAllInactive from '../../icons/view_all_inactif.svg';
import viewByAllActive from '../../icons/view_all_actif.svg';
import {
  labelDisplayView,
  labelViewByHost,
  labelViewByService,
  labelAll
} from '../../translatedLabels';
import { Visualization } from '../../models';

import Action from './Action';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(0.5)
  },
  text: {
    marginRight: theme.spacing(1.5)
  }
}));

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
    type: Visualization.SERVICE
  }
];
const VisualizationActions = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.container}>
      <Typography className={classes.text}>{t(labelDisplayView)}</Typography>
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
    </Box>
  );
};

export default VisualizationActions;
