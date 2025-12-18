import { Dialog, TextField, useRequest } from '@centreon/ui';

import { useFormik } from 'formik';
import { not, path } from 'ramda';
import { ChangeEvent, KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import {
  labelCancel,
  labelName,
  labelNewFilter,
  labelRequired,
  labelSave,
  labelUpdateFilter
} from '../../translatedLabels';
import { Filter } from '../models';

type InputChangeEvent = (event: ChangeEvent<HTMLInputElement>) => void;

interface Props {
  callbackSuccess?: (data) => void;
  onCancel: () => void;
  open: boolean;
  payloadAction: Record<string, unknown>;
  request;
}

const CreateFilterDialog = ({
  payloadAction,
  request,
  callbackSuccess,
  open,
  onCancel
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { sendRequest, sending } = useRequest<Filter>({
    request
  });
  const form = useFormik({
    initialValues: {
      name: payloadAction?.filter?.name || ''
    },
    onSubmit: (values) => {
      const payload = { ...payloadAction, name: values.name };

      sendRequest(payload)
        .then(callbackSuccess)
        .catch((requestError) => {
          form.setFieldError(
            'name',
            path(['response', 'data', 'message'], requestError)
          );
        });
    },
    validateOnMount: true,
    validationSchema: object().shape({
      name: string().required(labelRequired)
    })
  });

  const submitFormOnEnterKey = (event: KeyboardEvent<HTMLDivElement>): void => {
    const enterKeyPressed = event.keyCode === 13;

    if (enterKeyPressed) {
      form.submitForm();
    }
  };

  const isUpdatingFilter = !!payloadAction?.filter?.name;

  const confirmDisabled = not(form.isValid);

  return (
    <Dialog
      confirmDisabled={confirmDisabled}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelSave)}
      labelTitle={t(isUpdatingFilter ? labelUpdateFilter : labelNewFilter)}
      onCancel={onCancel}
      onConfirm={form.submitForm}
      open={open}
      submitting={sending}
    >
      <TextField
        ariaLabel={t(labelName) as string}
        autoFocus
        dataTestId={labelName}
        disabled={isUpdatingFilter}
        error={form.touched.name ? form.errors.name : undefined}
        label={t(labelName)}
        onChange={form.handleChange('name') as InputChangeEvent}
        onKeyDown={submitFormOnEnterKey}
        value={form.values.name}
      />
    </Dialog>
  );
};

export default CreateFilterDialog;
