import { ReactNode } from 'react';

import { Typography } from '@mui/material';

import { useStyles } from './deletion.styles';

interface Props {
  body: ReactNode;
}

const Message = ({ body }: Props): JSX.Element => {
  const { classes } = useStyles();

  return <Typography className={classes.labelMessage}>{body}</Typography>;
};

export default Message;
