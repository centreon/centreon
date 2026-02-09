import SaveIcon from '@mui/icons-material/SaveAlt';

import { IconButton } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Visualization } from '../../models';
import { labelExportToCSV } from '../../translatedLabels';
import { selectedVisualizationAtom } from '../actionsAtoms';
import ModalExport from './ModalExport';

const ExportCsv = () => {
  const { t } = useTranslation();
  const [display, setDisplay] = useState(false);
  const currentVisualization = useAtomValue(selectedVisualizationAtom);

  const openModalExport = () => {
    setDisplay(true);
  };

  const closeModalExport = () => {
    setDisplay(false);
  };

  return (
    <>
      <IconButton
        aria-label="exportCsvButton"
        disabled={!equals(Visualization.All, currentVisualization)}
        onClick={openModalExport}
        title={t(labelExportToCSV)}
      >
        <SaveIcon />
      </IconButton>
      <ModalExport onCancel={closeModalExport} open={display} />
    </>
  );
};

export default ExportCsv;
