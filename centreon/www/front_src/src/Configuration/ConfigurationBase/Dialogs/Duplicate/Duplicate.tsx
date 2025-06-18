import { NumberField } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { Trans, useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelDuplicate,
  labelDuplications
} from '../../translatedLabels';
import { useStyles } from './Duplicate.styles';
import useDuplicate from './useDuplicate';

const DuplicateDialog = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const {
    confirm,
    close,
    isMutating,
    duplicatesCount,
    changeDuplicateCount,
    isOpened,
    bodyContent,
    headerContent
  } = useDuplicate();

  return (
    <Modal open={isOpened} size="large" onClose={close}>
      <Modal.Header>{headerContent}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            defaults={bodyContent.label}
            values={bodyContent.value}
            components={{ bold: <strong /> }}
          />
        </Typography>
        <div className={classes.duplicationCount}>
          <Typography className={classes.duplicationCountTitle}>
            {t(labelDuplications)}
          </Typography>
          <NumberField
            autoSize
            autoSizeDefaultWidth={20}
            dataTestId={labelDuplications}
            defaultValue={duplicatesCount}
            disabled={isMutating}
            fallbackValue={1}
            textFieldSlotsAndSlotProps={{
              slotProps: {
                htmlInput: {
                  'aria-label': t(labelDuplications),
                  min: 1,
                  max: 10
                }
              }
            }}
            size="compact"
            type="number"
            onChange={changeDuplicateCount}
          />
        </div>
      </Modal.Body>
      <Modal.Actions
        disabled={isMutating}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDuplicate)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DuplicateDialog;
