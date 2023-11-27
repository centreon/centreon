import { useTranslation } from 'react-i18next';

import Button from '@mui/material/Button';
import { Typography } from '@mui/material';

import { labelCreateNewToken } from '../translatedLabels';
import { useStyles } from '../TokenListing/Actions/actions.styles';

const TokenCreationButton = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Button
      className={classes.buttonCreationToken}
      data-testid={labelCreateNewToken}
      size="small"
      variant="contained"
    >
      <Typography variant="body2">{t(labelCreateNewToken)}</Typography>
    </Button>
  );
};

export default TokenCreationButton;
