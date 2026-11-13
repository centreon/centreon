import { Modal } from '@centreon/ui/components';

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
import Warning from './Warning';

interface Props {
  hasReachedMaximumLinesToExport: boolean;
  isLoading: boolean;
  numberExportedLines: number;
  onCancel: () => void;
  onConfirm: () => void;
  onSelectColumns?: (id: string) => void;
  onSelectPages?: (id: string) => void;
  open: boolean;
}

// The export dialog shared by Resource Status and the Resource table widget.
// The selected options live in `defaultCheckedColumnAtom` and
// `defaultCheckedPageAtom`.
const ExportDialog = ({
  hasReachedMaximumLinesToExport,
  isLoading,
  numberExportedLines,
  onCancel,
  onConfirm,
  onSelectColumns,
  onSelectPages,
  open
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useExportCsvStyles();

  return (
    <Modal hasCloseButton={false} open={open} size="medium">
      <Modal.Header>{t(labelExportToCSV)}</Modal.Header>
      <Modal.Body>
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <div className={classes.radioButtonsContainer}>
              <RadioButtons<ColumnId>
                defaultChecked={defaultCheckedColumnAtom}
                getData={onSelectColumns}
                options={columnOptions}
                title={t(labelSelectColumns)}
              />
              <RadioButtons<PageId>
                defaultChecked={defaultCheckedPageAtom}
                getData={onSelectPages}
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
        onConfirm={onConfirm}
      />
    </Modal>
  );
};

export default ExportDialog;
