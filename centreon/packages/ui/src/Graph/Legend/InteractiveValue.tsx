import { Typography } from '@mui/material';

import { useStyles } from './Legend.styles';

interface Props {
  value?: string | null;
}

const InteractiveValue = ({ value }: Props): JSX.Element | null => {
  const { classes } = useStyles({});
  if (!value) {
    return null;
  }

  return (
    <Typography className={classes.legendValue} variant="h6">
      {value}
    </Typography>
  );
};

export default InteractiveValue;
