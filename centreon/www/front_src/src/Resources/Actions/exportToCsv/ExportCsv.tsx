import { IconButton } from '@centreon/ui';
import SaveIcon from '@mui/icons-material/SaveAlt';
import { useState } from 'react';
import ModalExport from './ModalExport';

const ExportCsv = () => {
  const [display, setDisplay] = useState(false);

  const openModalExport = () => {
    setDisplay(true);
  };

  const closeModalExport = () => {
    setDisplay(false);
  };

  return (
    <>
      <IconButton onClick={openModalExport}>
        <SaveIcon />
      </IconButton>
      {display && <ModalExport onCancel={closeModalExport} />}
    </>
  );
};

export default ExportCsv;
