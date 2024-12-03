import { IconButton } from '@centreon/ui';
import SaveIcon from '@mui/icons-material/SaveAlt';
import { useState } from 'react';
import ModalExport from './ModalExport';

const MemoizedExportCsv = () => {
  const [displayModalExport, setDisplayModalExport] = useState(false);

  const openModalExport = () => {
    setDisplayModalExport(true);
  };

  const closeModalExport = () => {
    setDisplayModalExport(false);
  };

  return (
    <>
      <IconButton onClick={openModalExport}>
        <SaveIcon />
      </IconButton>
      {displayModalExport && <ModalExport onCancel={closeModalExport} />}
    </>
  );
};

export default MemoizedExportCsv;
