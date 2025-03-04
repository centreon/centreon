import { IconButton } from '@centreon/ui';
import SaveIcon from '@mui/icons-material/SaveAlt';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useState } from 'react';
import { Visualization } from '../../models';
import { selectedVisualizationAtom } from '../actionsAtoms';
import ModalExport from './ModalExport';

const ExportCsv = () => {
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
        onClick={openModalExport}
        disabled={!equals(Visualization.All, currentVisualization)}
      >
        <SaveIcon />
      </IconButton>
      {display && <ModalExport onCancel={closeModalExport} />}
    </>
  );
};

export default ExportCsv;
