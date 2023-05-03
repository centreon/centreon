import { useTranslation } from 'react-i18next';
import { useParams } from 'react-router-dom';

import { Typography } from '@mui/material';

import { labelDashboard } from '../translatedLabels';

const Dashboard = (): JSX.Element => {
  const { t } = useTranslation();
  const { dashboardId } = useParams();

  return <Typography>{`${t(labelDashboard)} ${dashboardId}`}</Typography>;
};

export default Dashboard;
