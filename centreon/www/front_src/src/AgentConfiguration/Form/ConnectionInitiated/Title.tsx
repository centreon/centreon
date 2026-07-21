import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

interface Props {
  label: string;
}

const Title = ({ label }: Props): React.ReactElement => {
  const { t } = useTranslation();

  return (
    <Typography className="mb-[4px] text-primary-main" variant="h6">
      {t(label)}
    </Typography>
  );
};

export default Title;
