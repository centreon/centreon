import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelDashboards } from './translatedLabels';

const Dashboards = (): JSX.Element => {
  const { t } = useTranslation();

  return <Typography>{t(labelDashboards)}</Typography>;
};

export default Dashboards;
