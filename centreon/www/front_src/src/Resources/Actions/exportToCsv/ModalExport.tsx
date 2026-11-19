import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { defaultCheckedColumnAtom, defaultCheckedPageAtom } from './atoms';
import ExportDialog from './ExportDialog';
import { ColumnId, PageId } from './models';
import useExportCSV from './useExportCsv';

interface Props {
  onCancel: () => void;
  open: boolean;
}

const ModalExport = ({ onCancel, open }: Props): JSX.Element => {
  const isAllColumnsChecked = equals(
    useAtomValue(defaultCheckedColumnAtom),
    ColumnId.allColumns
  );
  const isAllPagesChecked = equals(
    useAtomValue(defaultCheckedPageAtom),
    PageId.allPages
  );

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
      open={open}
    />
  );
};

export default ModalExport;
