<<<<<<< HEAD
import { Responsive } from '@visx/visx';

import { Typography, Box } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { Responsive } from '@visx/visx';

import { Typography, Box, makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  lineText: {
    fontSize: theme.typography.body2.fontSize,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
}));

interface Props {
  line?: JSX.Element | string;
}

const DetailsLine = ({ line }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Responsive.ParentSize parentSizeStyles={{ height: 'auto' }}>
      {({ width }): JSX.Element => (
        <Typography component="div">
          <Box
            className={classes.lineText}
            lineHeight={1}
            style={{
              maxWidth: width || 'unset',
            }}
          >
            {line}
          </Box>
        </Typography>
      )}
    </Responsive.ParentSize>
  );
};

export default DetailsLine;
