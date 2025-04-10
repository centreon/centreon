import { Modal } from '@centreon/ui/components';
import { equals } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  labelAllColumns,
  labelAllPages,
  labelCancel,
  labelCurrentPageOnly,
  labelExport,
  labelExportToCSV,
  labelSelecetPages,
  labelSelectColumns,
  labelVisibleColumnsOnly
} from '../../translatedLabels';
import InformationsLine from './InformationsLine';
import Warning from './Warning';
import useExportCsvStyles from './exportCsv.styles';
import useExportCSV from './useExportCsv';
import RadioButtons from './RadioButtons';

interface Props {
  onCancel: () => void;
  open: boolean;
}

enum ColumnId {
  visibleColumns = 'visibleColumns',
  allColumns = 'allColumns'
}

enum PageId {
  currentPage = 'currentPage',
  allPages = 'allPages'
}

const columnOptions = [
  { id: ColumnId.visibleColumns, name: labelVisibleColumnsOnly },
  { id: ColumnId.allColumns, name: labelAllColumns }
];

const pageOptions = [
  { id: PageId.currentPage, name: labelCurrentPageOnly },
  { id: PageId.allPages, name: labelAllPages }
];

const ModalExport = ({ onCancel, open }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useExportCsvStyles();
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

  return (
    <Modal open={open} hasCloseButton={false} size="medium">
      <Modal.Header>{t(labelExportToCSV)}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <div className={classes.radioButtonsContainer}>
              <RadioButtons
                defaultChecked={ColumnId.allColumns}
                options={columnOptions}
                title={t(labelSelectColumns)}
                getData={getSelectedColumnsData}
              />
              <RadioButtons
                defaultChecked={PageId.allPages}
                options={pageOptions}
                title={t(labelSelecetPages)}
                getData={getSelectedPagesData}
              />
            </div>
            <InformationsLine
              numberExportedLines={numberExportedLines}
              hasReachedMaximumLinesToExport={hasReachedMaximumLinesToExport}
              isLoading={isLoading}
            />
          </div>
          <Warning />
        </div>
      </Modal.Body>
      <Modal.Actions
        labels={{ cancel: t(labelCancel), confirm: t(labelExport) }}
        onConfirm={exportCsv}
        onCancel={onCancel}
        disabled={isLoading}
      />
    </Modal>
  );
};

export default ModalExport;
