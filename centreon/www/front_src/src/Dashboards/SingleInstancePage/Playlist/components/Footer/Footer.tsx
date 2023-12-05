import { Box, Paper, Slide } from '@mui/material';

import { Dashboard } from '../../../../components/DashboardPlaylists/models';

import { useOpenFooter } from './useOpenFooter';
import { useFooterStyles } from './Footer.styles';
import Player from './Player';
import Dashboards from './Dashboards';

interface Props {
  dashboards: Array<Dashboard>;
}

const Footer = ({ dashboards }: Props): JSX.Element => {
  const { classes } = useFooterStyles();
  const { openFooter } = useOpenFooter();

  return (
    <Slide direction="up" in={openFooter}>
      <Paper className={classes.footerContainer}>
        <Box className={classes.footer}>
          <Player dashboards={dashboards} />
          <Dashboards dashboards={dashboards} />
        </Box>
      </Paper>
    </Slide>
  );
};

export default Footer;
