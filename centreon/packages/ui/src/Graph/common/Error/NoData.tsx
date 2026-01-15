import { Typography } from "@mui/material";
import { useTranslation } from "react-i18next";
import { labelNoDataForThisPeriod } from "../../Chart/translatedLabels";

const NoData = () => {
	const { t } = useTranslation();
	return (
		<div className={"flex items-center justify-center h-full"}>
			<Typography align="center" variant="body1">
				{t(labelNoDataForThisPeriod)}
			</Typography>
		</div>
	);
};

export default NoData;
