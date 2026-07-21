import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

interface Props {
  title: string;
}

const SectionTitle = ({ title }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Typography sx={{ lineHeight: 1 }} variant="h6">
      {t(title)}
    </Typography>
  );
};

export default SectionTitle;
