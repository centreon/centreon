import { ReactElement } from 'react';

import { Box, Typography } from '@mui/material';

import { useStyles } from './PageHeader.styles';

type PageHeaderMessageProps = {
  message: string;
};

const PageHeaderMessage = ({
  message
}: PageHeaderMessageProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <Box className={classes.pageHeaderMessage}>
      {message && (
        <>
          <Box className={classes.pageHeaderMessageIconWrapper}>
            <Box className={classes.pageHeaderMessageIcon}>!</Box>
          </Box>
          <Typography aria-label="page header message" variant="h6">
            {message}
          </Typography>
        </>
      )}
    </Box>
  );
};

export { PageHeaderMessage };
