import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { WidgetPropertyProps } from '../../models';

const Text = ({ label }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  return <Typography>{t(label)}</Typography>;
};

export default Text;
