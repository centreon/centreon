import SaveIcon from '@mui/icons-material/SaveAlt';

import { IconButton } from '@centreon/ui';

import { equals } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import ExportDialog from '../../../../../../../../Resources/Actions/exportToCsv/ExportDialog';
import { labelExportToCSV } from '../../../../../../../../Resources/translatedLabels';
import { DisplayType, type ExportParameters } from '../models';
import useExportCsv from './useExportCsv';

interface Props {
  parameters: ExportParameters;
}

const ExportCsv = ({ parameters }: Props): JSX.Element => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);

  const {
    exportCsv,
    hasReachedMaximumLinesToExport,
    isLoading,
    numberExportedLines
  } = useExportCsv({ isOpen, parameters });

  const open = (): void => {
    setIsOpen(true);
  };

  const close = (): void => {
    setIsOpen(false);
  };

  const confirm = (): void => {
    exportCsv();
    close();
  };

  return (
    <>
      <IconButton
        ariaLabel={t(labelExportToCSV)}
        disabled={!equals(parameters.displayType, DisplayType.All)}
        onClick={open}
        title={t(labelExportToCSV)}
      >
        <SaveIcon />
      </IconButton>
      <ExportDialog
        hasReachedMaximumLinesToExport={hasReachedMaximumLinesToExport}
        isLoading={isLoading}
        numberExportedLines={numberExportedLines}
        onCancel={close}
        onConfirm={confirm}
        open={isOpen}
      />
    </>
  );
};

export default ExportCsv;
