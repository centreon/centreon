import { Modal } from '@centreon/ui/components';
import { equals } from 'ramda';
import { useState } from 'react';
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
import { CheckedValue } from './models';
import useExportCSV from './useExportCsv';

interface Props {
  onCancel: () => void;
}

const ModalExport = ({ onCancel }: Props) => {
  const { classes } = useExportCsvStyles();
  const [allPages, setAllPages] = useState(true);
  const [allColumns, setAllColumns] = useState(true);

  const exportCsv = useExportCSV({ allColumns, allPages });

  const getCheckedValue = ({
    defaultLabel,
    label,
    value
  }: CheckedValue): boolean => {
    return equals(defaultLabel, label) ? value : false;
  };

  const getSelectedColumnsData = ({ label, value }) => {
    const checkedValue = getCheckedValue({
      label,
      value,
      defaultLabel: labelAllColumns
    });
    setAllColumns(checkedValue);
  };

  const getSelectedPagesData = ({ label, value }) => {
    const checkedValue = getCheckedValue({
      label,
      value,
      defaultLabel: labelAllPages
    });
    setAllPages(checkedValue);
  };

  return (
    <Modal open hasCloseButton={false}>
      <Modal.Header>{labelExportToCSV}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <div className={classes.checkBoxContainer}>
              <CheckBoxScope
                defaultCheckedLabel={{ label: labelAllColumns, value: true }}
                labels={{
                  firstLabel: labelVisibleColumnsOnly,
                  secondLabel: labelAllColumns
                }}
                title={labelSelectColumns}
                getData={getSelectedColumnsData}
              />
              <div className={classes.spacing} />
              <CheckBoxScope
                defaultCheckedLabel={{ label: labelAllPages, value: true }}
                labels={{
                  firstLabel: labelCurrentPageOnly,
                  secondLabel: labelAllPages
                }}
                title={labelSelecetPages}
                getData={getSelectedPagesData}
              />
            </div>
            <InformationsLine data="" />
          </div>
          <div className={classes.spacing} />
          <Warning />
        </div>
      </Modal.Body>
      <Modal.Actions
        labels={{ cancel: labelCancel, confirm: labelExport }}
        onConfirm={exportCsv}
        onCancel={onCancel}
      />
    </Modal>
  );
};

export default ModalExport;
