import { useTranslation } from 'react-i18next';

import {
  Box,
  Typography,
  FormControlLabel,
  Radio,
  RadioGroup
} from '@mui/material';

import { Modal } from '@centreon/ui/components';
import { Subtitle } from '@centreon/ui';

import {
  labelCancel,
  labelExport,
  labelExportToCSV,
  labelExportToCSVWarning,
  labelSelectColumns,
  labelVisibleColumnsOnly,
  labelAllColumns,
  labelCurrentPageOnly,
  labelSelectPages,
  labelAllPages
} from '../../translatedLabels';

import { ColumnsOption, PagesOption } from './models';
import { useExportToCSV } from './useExportToCSVstyles';
import useExportToCSVDialog from './useExportToCSVDialog';

const ExportCSVDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useExportToCSV();

  const {
    changeExportedColumns,
    changeExportedPages,
    columnsToExport,
    pagesToExport,
    isOpen,
    close,
    exportToCSV,
    loading
  } = useExportToCSVDialog();

  return (
    <Modal open={isOpen} size="medium" onClose={close}>
      <Modal.Header>{labelExportToCSV}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <Box>
            <Subtitle>{t(labelSelectColumns)}</Subtitle>
            <RadioGroup
              value={columnsToExport}
              onChange={changeExportedColumns}
            >
              <FormControlLabel
                control={<Radio />}
                label={t(labelVisibleColumnsOnly)}
                value={ColumnsOption.Visible}
              />
              <FormControlLabel
                control={<Radio />}
                label={t(labelAllColumns)}
                value={ColumnsOption.All}
              />
            </RadioGroup>
          </Box>

          <Box>
            <Subtitle>{t(labelSelectPages)}</Subtitle>
            <RadioGroup value={pagesToExport} onChange={changeExportedPages}>
              <FormControlLabel
                control={<Radio />}
                label={t(labelCurrentPageOnly)}
                value={PagesOption.Current}
              />
              <FormControlLabel
                control={<Radio />}
                label={t(labelAllPages)}
                value={t(PagesOption.All)}
              />
            </RadioGroup>
          </Box>

          <Box className={classes.warningBox}>
            <Typography>{t(labelExportToCSVWarning)}</Typography>
          </Box>
        </div>
      </Modal.Body>
      <Modal.Actions
        disabled={loading}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelExport)
        }}
        loading={loading}
        onCancel={close}
        onConfirm={exportToCSV}
      />
    </Modal>
  );
};

export default ExportCSVDialog;
