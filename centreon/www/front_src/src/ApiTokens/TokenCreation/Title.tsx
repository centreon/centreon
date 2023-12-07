import { Typography } from '@mui/material';

import { useStyles } from '../TokenListing/tokenListing.styles';
import { labelCreateNewToken } from '../translatedLabels';

const Title = ({ token }): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div>
      {token ? (
        <div style={{ maxWidth: 450 }}>
          <Typography className={classes.title} variant="h6">
            Token has been created
          </Typography>
          <Typography variant="subtitle2">
            For security reasons, the token can only be displayed once.Remember
            to store it well.
          </Typography>
        </div>
      ) : (
        <Typography className={classes.title} variant="h6">
          {labelCreateNewToken}
        </Typography>
      )}
    </div>
  );
};

export default Title;
