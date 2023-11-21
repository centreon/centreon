import Button from '@mui/material/Button';
import { Typography } from '@mui/material';

import { labelCreateNewToken } from '../translatedLabels';
import { useStyles } from '../tokenListing/actions/actions.styles';

const TokenCreationButton = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Button
      className={classes.buttonCreationToken}
      data-testid={labelCreateNewToken}
      size="small"
      variant="contained"
    >
      <Typography variant="body2">{labelCreateNewToken}</Typography>
    </Button>
  );
};

export default TokenCreationButton;
