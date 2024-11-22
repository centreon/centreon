import { makeStyles } from 'tss-react/mui';

import { Box, Typography } from '@mui/material';

import { ParentSize } from '@centreon/ui';

const useStyles = makeStyles()((theme) => ({
  lineText: {
    fontSize: theme.typography.body2.fontSize,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

interface Props {
  line?: JSX.Element | string;
}

const DetailsLine = ({ line }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <ParentSize parentSizeStyles={{ height: 'auto' }}>
      {({ width }): JSX.Element => (
        <Typography component="div">
          <Box
            className={classes.lineText}
            lineHeight={1}
            style={{
              maxWidth: width || 'unset'
            }}
          >
            {line}
          </Box>
        </Typography>
      )}
    </ParentSize>
  );
};

export default DetailsLine;
