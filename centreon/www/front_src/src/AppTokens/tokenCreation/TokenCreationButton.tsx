import Button from '@mui/material/Button';

import { labelCreateNewToken } from '../translatedLabels';

const TokenCreationButton = (): JSX.Element => {
  return (
    <Button data-testid={labelCreateNewToken} size="large">
      {labelCreateNewToken}
    </Button>
  );
};

export default TokenCreationButton;
