import { Modal } from '@centreon/ui/components';
import { equals } from 'ramda';
import { useState } from 'react';
import { labelCancel } from '../../translatedLabels';
import CheckBoxCriter from './CheckBoxCriter';
import useExportCSV from './useExportCsv';

const labelVisibleColumnsOnly = 'Visible columns only';
const labelAllColumns = 'All columns';
const labelAllPages = 'All pages';
const labelCurrentPageOnly = 'Current page only';

const ModalExport = ({ onCancel }) => {
  const [allPages, setAllPages] = useState(true);
  const [allColumns, setAllColumns] = useState(true);

  const exportCsv = useExportCSV({ allColumns, allPages });

  const getCheckedValue = ({ defaultLabel, label, value }) => {
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
    <Modal open>
      <Modal.Header>title</Modal.Header>
      <Modal.Body>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <div style={{ display: 'flex', flexDirection: 'row' }}>
            <div style={{ flex: 0.5 }}>
              <CheckBoxCriter
                defaultLabel={labelAllColumns}
                labels={{
                  firstLabel: labelVisibleColumnsOnly,
                  secondLabel: labelAllColumns
                }}
                title="Select columns"
                getData={getSelectedColumnsData}
              />
              <CheckBoxCriter
                defaultLabel={labelAllPages}
                labels={{
                  firstLabel: labelCurrentPageOnly,
                  secondLabel: labelAllPages
                }}
                title="Select Pages"
                getData={getSelectedPagesData}
              />
            </div>
            <div style={{ background: 'grey', flex: 0.5 }}>
              expliquation about lines
            </div>
          </div>
          <div> warning</div>
        </div>
      </Modal.Body>
      <Modal.Actions
        labels={{ cancel: labelCancel, confirm: 'confirm' }}
        onConfirm={exportCsv}
        onCancel={onCancel}
      />
    </Modal>
  );
};

export default ModalExport;
