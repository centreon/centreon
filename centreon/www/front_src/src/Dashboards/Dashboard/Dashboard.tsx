import { Typography } from "@mui/material";
import { useTranslation } from "react-i18next";
import { labelDashboard } from "../translatedLabels";
import { useParams } from "react-router-dom";

const Dashboard = () => {
  const { t } = useTranslation();
  const { dashboardId } = useParams();

  return (
    <Typography>{`${t(labelDashboard)} ${dashboardId}`}</Typography>
  )
}

export default Dashboard;