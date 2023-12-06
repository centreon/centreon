import { useRef } from 'react';

import { Box, Paper, Slide, Divider } from '@mui/material';

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
  const playlistFooterRef = useRef<HTMLDivElement | null>(null);
  const { openFooter } = useOpenFooter(playlistFooterRef);

  return (
    <Slide direction="up" in={openFooter}>
      <Paper
        className={classes.footerContainer}
        id="footer"
        ref={playlistFooterRef}
      >
        <Box className={classes.footer}>
          <Player dashboards={dashboards} />
          <Divider className={classes.divider} orientation="vertical" />
          <Dashboards dashboards={dashboards} />
        </Box>
      </Paper>
    </Slide>
  );
};

export default Footer;
