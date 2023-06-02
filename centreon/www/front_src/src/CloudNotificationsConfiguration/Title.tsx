import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelNotificationsManagement } from './translatedLabels';

interface Props {
  className?: string;
}

const Title = ({ className }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Typography className={className} variant="h6">
      {t(labelNotificationsManagement)}
    </Typography>
  );
};

export default Title;
