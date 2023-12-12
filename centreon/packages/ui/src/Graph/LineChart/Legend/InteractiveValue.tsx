import { Typography } from '@mui/material';

import { useLegendValueStyles } from './Legend.styles';

interface Props {
  value?: string | null;
}

const InteractiveValue = ({ value }: Props): JSX.Element | null => {
  const { classes } = useLegendValueStyles({});
  if (!value) {
    return null;
  }

  return (
    <Typography className={classes.text} variant="h6">
      {value}
    </Typography>
  );
};

export default InteractiveValue;
