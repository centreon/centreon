import { equals } from 'ramda';
import { useState } from 'react';

import ExportDialog from './ExportDialog';
import { ColumnId, PageId } from './models';
import useExportCSV from './useExportCsv';

interface Props {
  onCancel: () => void;
  open: boolean;
}

const ModalExport = ({ onCancel, open }: Props): JSX.Element => {
  const [isAllPagesChecked, setIsAllPagesChecked] = useState(true);
  const [isAllColumnsChecked, setIsAllColumnsChecked] = useState(true);

  const {
    exportCsv,
    hasReachedMaximumLinesToExport,
    numberExportedLines,
    isLoading
  } = useExportCSV({
    isAllColumnsChecked,
    isAllPagesChecked,
    isOpen: open
  });

  const getSelectedColumnsData = (id: string) => {
    setIsAllColumnsChecked(equals(ColumnId.allColumns, id));
  };

  const getSelectedPagesData = (id: string) => {
    setIsAllPagesChecked(equals(PageId.allPages, id));
  };

  const confirm = () => {
    exportCsv();
    onCancel();
  };

  return (
    <ExportDialog
      hasReachedMaximumLinesToExport={hasReachedMaximumLinesToExport}
      isLoading={isLoading}
      numberExportedLines={numberExportedLines}
      onCancel={onCancel}
      onConfirm={confirm}
      onSelectColumns={getSelectedColumnsData}
      onSelectPages={getSelectedPagesData}
      open={open}
    />
  );
};

export default ModalExport;
