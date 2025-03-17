import { Modal } from '@centreon/ui/components';
import { equals } from 'ramda';
import { useCallback, useMemo, useState } from 'react';
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
import CheckBoxScope from './CheckBoxScope';
import InformationsLine from './InformationsLine';
import Warning from './Warning';
import useExportCsvStyles from './exportCsv.styles';
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

  const { exportCsv, disableExport, numberExportedLines } = useExportCSV({
    isAllColumnsChecked,
    isAllPagesChecked
  });

  const getSelectedColumnsData = useCallback((label: string) => {
    setIsAllColumnsChecked(equals(labelAllColumns, label));
  }, []);

  const getSelectedPagesData = useCallback((label: string) => {
    setIsAllPagesChecked(equals(labelAllPages, label));
  }, []);

  const defaultAllColumnsLabel = useMemo(
    () => ({ label: t(labelAllColumns), isChecked: true }),
    []
  );
  const defaultVisibleColumnsLabel = useMemo(
    () => ({
      firstLabel: t(labelVisibleColumnsOnly),
      secondLabel: t(labelAllColumns)
    }),
    []
  );
  const defaultAllPagesLabel = useMemo(
    () => ({ label: t(labelAllPages), isChecked: true }),
    []
  );
  const defaultVisiblePagesLabel = useMemo(
    () => ({
      firstLabel: t(labelCurrentPageOnly),
      secondLabel: t(labelAllPages)
    }),
    []
  );

  return (
    <Modal open={open} hasCloseButton={false} size="medium">
      <Modal.Header>{t(labelExportToCSV)}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <div className={classes.checkBoxContainer}>
              <CheckBoxScope
                defaultCheckedLabel={defaultAllColumnsLabel}
                labels={defaultVisibleColumnsLabel}
                title={t(labelSelectColumns)}
                getData={getSelectedColumnsData}
              />
              <div className={classes.spacing} />
              <CheckBoxScope
                defaultCheckedLabel={defaultAllPagesLabel}
                labels={defaultVisiblePagesLabel}
                title={t(labelSelecetPages)}
                getData={getSelectedPagesData}
              />
            </div>
            <InformationsLine
              numberExportedLines={numberExportedLines}
              disableExport={disableExport}
            />
          </div>
          <div className={classes.spacing} />
          <Warning />
        </div>
      </Modal.Body>
      <Modal.Actions
        labels={{ cancel: t(labelCancel), confirm: t(labelExport) }}
        onConfirm={exportCsv}
        onCancel={onCancel}
        disabled={disableExport}
      />
    </Modal>
  );
};

export default ModalExport;
