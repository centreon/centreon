import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { equals, isEmpty } from 'ramda';
import {
  labelCancel,
  labelDuplicate,
  labelDuplicateConfirmationText,
  labelDuplicateConfirmationTitle,
  labelDuplications
} from '../../../translatedLabels';
import useDuplicate from './useDuplicate';

import { useStyles } from './Duplicate.styles';

const DuplicateDialog = (): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const {
    confirm,
    close,
    isMutating,
    duplicatesCount,
    changeDuplicateCount,
    hostGroupsToDuplicate
  } = useDuplicate();

  return (
    <Modal open={!isEmpty(hostGroupsToDuplicate)} size="large" onClose={close}>
      <Modal.Header>{t(labelDuplicateConfirmationTitle)}</Modal.Header>
      <Modal.Body>
        <Typography>
          {t(labelDuplicateConfirmationText, {
            name: equals(hostGroupsToDuplicate.length, 1)
              ? hostGroupsToDuplicate[0]?.name
              : hostGroupsToDuplicate.length
          })}
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
            disabled={false}
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
