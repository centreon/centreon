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
import { defaultCheckedColumnAtom, defaultCheckedPageAtom } from './atoms';
import useExportCsvStyles from './exportCsv.styles';
import InformationsLine from './InformationsLine';
import { ColumnId, columnOptions, PageId, pageOptions } from './models';
import RadioButtons from './RadioButtons';
import useExportCSV from './useExportCsv';
import Warning from './Warning';

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
    <Modal hasCloseButton={false} open={open} size="medium">
      <Modal.Header>{t(labelExportToCSV)}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <div className={classes.radioButtonsContainer}>
              <RadioButtons<ColumnId>
                defaultChecked={defaultCheckedColumnAtom}
                getData={getSelectedColumnsData}
                options={columnOptions}
                title={t(labelSelectColumns)}
              />
              <RadioButtons<PageId>
                defaultChecked={defaultCheckedPageAtom}
                getData={getSelectedPagesData}
                options={pageOptions}
                title={t(labelSelecetPages)}
              />
            </div>
            <InformationsLine
              hasReachedMaximumLinesToExport={hasReachedMaximumLinesToExport}
              isLoading={isLoading}
              numberExportedLines={numberExportedLines}
            />
          </div>
          <Warning />
        </div>
      </Modal.Body>
      <Modal.Actions
        disabled={isLoading}
        labels={{ cancel: t(labelCancel), confirm: t(labelExport) }}
        onCancel={onCancel}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default ModalExport;
