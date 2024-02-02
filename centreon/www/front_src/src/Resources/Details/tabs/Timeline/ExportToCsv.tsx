import { useTranslation } from 'react-i18next';

import SaveIcon from '@mui/icons-material/SaveAlt';
import { Button, Stack, Typography } from '@mui/material';

import { getSearchQueryParameterValue } from '@centreon/ui';
import type { SearchParameter } from '@centreon/ui';

import { labelExportToCSV } from '../../../translatedLabels';

interface Props {
  getSearch: () => SearchParameter | undefined;
  timelineDownloadEndpoint: string;
}

const ExportToCsv = ({
  getSearch,
  timelineDownloadEndpoint
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const exportToCsv = (): void => {
    const data = getSearch();

    const parameters = getSearchQueryParameterValue(data);
    const exportToCSVEndpoint = `${timelineDownloadEndpoint}?search=${JSON.stringify(
      parameters
    )}`;

    window.open(exportToCSVEndpoint, 'noopener', 'noreferrer');
  };

  return (
    <Stack direction="row" justifyContent="flex-end">
      <Button
        data-testid={labelExportToCSV}
        size="small"
        startIcon={<SaveIcon />}
        variant="contained"
        onClick={exportToCsv}
      >
        <Typography variant="body2"> {t(labelExportToCSV)} </Typography>
      </Button>
    </Stack>
  );
};

export default ExportToCsv;
