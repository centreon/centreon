import { Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

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
    getBodyContent,
    headerContent,
    isSingleDuplicate
  } = useDuplicate();

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{headerContent}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            components={{ bold: <strong /> }}
            defaults={getBodyContent().label}
            values={getBodyContent().value}
          />
        </Typography>
        {!isSingleDuplicate && (
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
              onChange={changeDuplicateCount}
              size="compact"
              textFieldSlotsAndSlotProps={{
                slotProps: {
                  htmlInput: {
                    'aria-label': t(labelDuplications),
                    max: 10,
                    min: 1
                  }
                }
              }}
              type="number"
            />
          </div>
        )}
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
