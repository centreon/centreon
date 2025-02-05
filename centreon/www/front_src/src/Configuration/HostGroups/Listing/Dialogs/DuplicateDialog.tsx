import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import {
  Method,
  NumberField,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { equals, isEmpty, pluck } from 'ramda';
import { useState } from 'react';
import { duplicateHostGroupEndpoint } from '../../api/endpoints';
import { hostGroupsToDuplicateAtom } from '../../atoms';
import {
  labelCancel,
  labelDuplicate,
  labelDuplicateConfirmationText,
  labelDuplicateConfirmationTitle,
  labelDuplications,
  labelHostGroupDuplicated
} from '../../translatedLabels';
import { useStyles } from './Dialog.styles';

const DuplicateDialog = (): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [duplicatesCount, setDuplicatesCount] = useState(1);

  const [hostGroupsToDuplicate, setHostGroupsToDuplicate] = useAtom(
    hostGroupsToDuplicateAtom
  );

  const close = (): void => {
    setHostGroupsToDuplicate([]);
  };

  const { isMutating, mutateAsync: duplicateHostGroup } = useMutationQuery({
    getEndpoint: () => duplicateHostGroupEndpoint,
    method: Method.POST,
    onSettled: close,
    onSuccess: () => {
      showSuccessMessage(t(labelHostGroupDuplicated));
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const confirm = (): void => {
    duplicateHostGroup({
      payload: {
        ids: pluck('id', hostGroupsToDuplicate),
        nb_duplicates: duplicatesCount
      }
    });
  };

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
            onChange={(inputValue: number) => setDuplicatesCount(inputValue)}
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
