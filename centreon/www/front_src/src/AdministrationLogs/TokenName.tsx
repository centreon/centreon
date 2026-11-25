import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelViaToken } from './translatedLabels';

interface Props {
  name: string;
}

// Secondary to the author, like the parent of an object
const TokenName = ({ name }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Typography
      className="truncate text-text-secondary"
      component="span"
      variant="caption"
    >
      {t(labelViaToken, { token: name })}
    </Typography>
  );
};

export default TokenName;
