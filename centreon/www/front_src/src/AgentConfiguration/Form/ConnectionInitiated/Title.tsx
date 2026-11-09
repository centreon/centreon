import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

interface Props {
  label: string;
}

const Title = ({ label }: Props): React.ReactElement => {
  const { t } = useTranslation();

  return (
    <Typography
      className="mb-1 text-base font-bold text-text-primary"
      variant="h6"
    >
      {t(label)}
    </Typography>
  );
};

export default Title;
