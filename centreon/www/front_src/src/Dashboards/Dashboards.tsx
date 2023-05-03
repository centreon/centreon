import { Typography } from "@mui/material";
import { useTranslation } from "react-i18next";
import { labelDashboards } from "./translatedLabels";

const Dashboards = () => {
  const { t } = useTranslation();

  return (
    <Typography>{t(labelDashboards)}</Typography>
  )
}

export default Dashboards;