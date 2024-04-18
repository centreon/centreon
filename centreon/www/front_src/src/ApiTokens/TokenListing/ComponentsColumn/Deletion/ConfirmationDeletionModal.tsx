import { ReactNode, useState } from 'react';

import { useTranslation } from 'react-i18next';

import { ConfirmDialog, useMutationQuery, useSnackbar } from '@centreon/ui';

import { Meta } from '../../../api/models';
import {
  labelCancel,
  labelDelete,
  labelTokenDeletedSuccessfully
} from '../../../translatedLabels';
import useRefetch from '../../../useRefetch';

import Title from './Title';
import { useStyles } from './deletion.styles';
import { DataApi } from './models';

interface Props {
  close: () => void;
  dataApi: DataApi;
  labelDeletedSuccessfully?: string;
  msg?: ReactNode;
  title?: string;
}

const ConfirmationDeletionModal = ({
  close,
  dataApi,
  labelDeletedSuccessfully = labelTokenDeletedSuccessfully,
  title,
  msg
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const [isDataRetrieved, setIDataRetrieved] = useState(false);
  const { isRefetching } = useRefetch({
    key: isDataRetrieved,
    onSuccess: () => close()
  });
  const { getEndpoint, dataMutation, method, decoder } = dataApi;

  const { _meta, payload } = dataMutation || {};

  const success = (): void => {
    showSuccessMessage(t(labelDeletedSuccessfully));
    setIDataRetrieved(true);
  };

  const { mutateAsync, isMutating } = useMutationQuery<object, Meta>({
    baseEndpoint: 'http://localhost:3000/centreon/api/latest',
    decoder,
    getEndpoint,
    method,
    onSuccess: success
  });

  const deleteToken = (): void => {
    mutateAsync({ _meta, payload });
  };

  const disabled = isMutating || isRefetching;

  return (
    <ConfirmDialog
      open
      cancelDisabled={disabled}
      confirmDisabled={disabled}
      data-testid="deleteDialog"
      dialogTitleClassName={classes.title}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelTitle={<Title title={title} />}
      restCancelButtonProps={{ variant: 'outlined' }}
      restConfirmButtonProps={{
        classes: { root: classes.confirmButton },
        color: 'error',
        variant: 'contained'
      }}
      submitting={isMutating}
      onCancel={close}
      onConfirm={deleteToken}
    >
      {msg}
    </ConfirmDialog>
  );
};

export default ConfirmationDeletionModal;
