import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import ExportIcon from '@mui/icons-material/FileDownloadOutlined';

import { IconButton } from '@centreon/ui';

import { labelExportToCSV } from '../../translatedLabels';
import { isExportToCSVDialogOpenAtom } from '../actionsAtoms';

const ExportToCSV = (): JSX.Element => {
  const { t } = useTranslation();
  const openDialog = useSetAtom(isExportToCSVDialogOpenAtom);

  return (
    <IconButton
      data-testid={labelExportToCSV}
      size="large"
      title={t(labelExportToCSV)}
      onClick={(): void => openDialog(true)}
    >
      <ExportIcon />
    </IconButton>
  );
};

export default ExportToCSV;
