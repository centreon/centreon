import { Modal } from '@centreon/ui/components';
import { equals } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  labelCancel,
  labelExport,
  labelExportToCSV,
  labelSelecetPages,
  labelSelectColumns
} from '../../translatedLabels';
import InformationsLine from './InformationsLine';
import RadioButtons from './RadioButtons';
import Warning from './Warning';
import { defaultCheckedColumnAtom, defaultCheckedPageAtom } from './atoms';
import useExportCsvStyles from './exportCsv.styles';
import { ColumnId, PageId, columnOptions, pageOptions } from './models';
import useExportCSV from './useExportCsv';

interface Props {
  onCancel: () => void;
  open: boolean;
}

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

  const confirm = () => {
    exportCsv();
    onCancel();
  };

  return (
    <Modal open={open} hasCloseButton={false} size="medium">
      <Modal.Header>{t(labelExportToCSV)}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <div className={classes.radioButtonsContainer}>
              <RadioButtons<ColumnId>
                defaultChecked={defaultCheckedColumnAtom}
                options={columnOptions}
                title={t(labelSelectColumns)}
                getData={getSelectedColumnsData}
              />
              <RadioButtons<PageId>
                defaultChecked={defaultCheckedPageAtom}
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
        onConfirm={confirm}
        onCancel={onCancel}
        disabled={isLoading}
      />
    </Modal>
  );
};

export default ModalExport;
