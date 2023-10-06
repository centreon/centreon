import { ChangeEvent, KeyboardEvent } from 'react';

import { useFormik } from 'formik';
import { equals, not, or, path } from 'ramda';
import { useTranslation } from 'react-i18next';
import * as Yup from 'yup';

import { Dialog, TextField, useRequest } from '@centreon/ui';

import {
  labelCancel,
  labelName,
  labelNewFilter,
  labelRequired,
  labelSave
} from '../../translatedLabels';
import { Action } from '../Criterias/models';
import { Filter } from '../models';

type InputChangeEvent = (event: ChangeEvent<HTMLInputElement>) => void;

interface Props {
  action?: Action;
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
  onCancel,
  action = Action.create
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { sendRequest, sending } = useRequest<Filter>({
    request
  });
  const form = useFormik({
    initialValues: {
      name: ''
    },
    onSubmit: (values) => {
      const payloadCreation = { ...payloadAction, name: values.name };
      const payloadUpdate = {
        ...payloadAction,
        filter: { ...payloadAction.filter, name: values.name }
      };

      const payload = equals(action, Action.create)
        ? payloadCreation
        : payloadUpdate;

      sendRequest(payload)
        .then(callbackSuccess)
        .catch((requestError) => {
          form.setFieldError(
            'name',
            path(['response', 'data', 'message'], requestError)
          );
        });
    },
    validationSchema: Yup.object().shape({
      name: Yup.string().required(labelRequired)
    })
  });

  const submitFormOnEnterKey = (event: KeyboardEvent<HTMLDivElement>): void => {
    const enterKeyPressed = event.keyCode === 13;

    if (enterKeyPressed) {
      form.submitForm();
    }
  };

  const confirmDisabled = or(not(form.isValid), not(form.dirty));

  return (
    <Dialog
      confirmDisabled={confirmDisabled}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelSave)}
      labelTitle={t(labelNewFilter)}
      open={open}
      submitting={sending}
      onCancel={onCancel}
      onConfirm={form.submitForm}
    >
      <TextField
        autoFocus
        ariaLabel={t(labelName) as string}
        error={form.errors.name}
        label={t(labelName)}
        value={form.values.name}
        onChange={form.handleChange('name') as InputChangeEvent}
        onKeyDown={submitFormOnEnterKey}
      />
    </Dialog>
  );
};

export default CreateFilterDialog;
