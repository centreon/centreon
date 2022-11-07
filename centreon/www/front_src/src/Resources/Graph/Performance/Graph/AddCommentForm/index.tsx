<<<<<<< HEAD
import { ChangeEvent, useState } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import { useTranslation } from 'react-i18next';
import { isEmpty, isNil, pipe, trim } from 'ramda';

<<<<<<< HEAD
import { Grid, Typography } from '@mui/material';
=======
import { Grid, Typography } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import {
  Dialog,
  TextField,
  useSnackbar,
  useRequest,
  useLocaleDateTimeFormat,
} from '@centreon/ui';

import {
  labelAdd,
  labelAddComment,
  labelComment,
  labelRequired,
  labelCommentAdded,
} from '../../../../translatedLabels';
import { commentResources } from '../../../../Actions/api';
import { Resource } from '../../../../models';
import { ResourceDetails } from '../../../../Details/models';

interface Props {
  date: Date;
  onClose: () => void;
  onSuccess: (comment) => void;
  resource: Resource | ResourceDetails;
}

const AddCommentForm = ({
  onClose,
  onSuccess,
  resource,
  date,
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { toIsoString, toDateTime } = useLocaleDateTimeFormat();
  const { showSuccessMessage } = useSnackbar();
<<<<<<< HEAD
  const [comment, setComment] = useState<string>();
=======
  const [comment, setComment] = React.useState<string>();
>>>>>>> centreon/dev-21.10.x

  const { sendRequest, sending } = useRequest({
    request: commentResources,
  });

<<<<<<< HEAD
  const changeComment = (event: ChangeEvent<HTMLInputElement>): void => {
=======
  const changeComment = (event: React.ChangeEvent<HTMLInputElement>): void => {
>>>>>>> centreon/dev-21.10.x
    setComment(event.target.value);
  };

  const confirm = (): void => {
    const parameters = {
      comment,
      date: toIsoString(date),
    };

    sendRequest({
      parameters,
      resources: [resource],
    }).then(() => {
      showSuccessMessage(t(labelCommentAdded));
      onSuccess(parameters);
    });
  };

  const getError = (): string | undefined => {
    if (isNil(comment)) {
      return undefined;
    }

    const normalizedComment = comment || '';

    return pipe(trim, isEmpty)(normalizedComment)
      ? t(labelRequired)
      : undefined;
  };

  const canConfirm = isNil(getError()) && !isNil(comment) && !sending;

  return (
    <Dialog
      open
      confirmDisabled={!canConfirm}
      labelConfirm={t(labelAdd)}
      labelTitle={t(labelAddComment)}
      submitting={sending}
      onCancel={onClose}
      onClose={onClose}
      onConfirm={confirm}
    >
      <Grid container direction="column" spacing={2}>
        <Grid item>
          <Typography variant="h6">{toDateTime(date)}</Typography>
        </Grid>
        <Grid item>
          <TextField
            autoFocus
            multiline
            required
            ariaLabel={t(labelComment)}
            error={getError()}
            label={t(labelComment)}
            rows={3}
            style={{ width: 300 }}
            value={comment}
            onChange={changeComment}
          />
        </Grid>
      </Grid>
    </Dialog>
  );
};

export default AddCommentForm;
