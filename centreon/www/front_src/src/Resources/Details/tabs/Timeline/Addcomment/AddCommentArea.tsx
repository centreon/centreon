import { ChangeEvent, useEffect, useState } from 'react';

import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import LoadingButton from '@mui/lab/LoadingButton';
import Button from '@mui/material/Button';

import {
  Method,
  ResponseError,
  TextField,
  useLocaleDateTimeFormat,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { commentEndpoint } from '../../../../Actions/api/endpoint';
import {
  labelCancel,
  labelComment,
  labelSave,
  labelYourCommentSent
} from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';

import { useStyles } from './addComment.styles';

interface Props {
  closeCommentArea: () => void;
  resources: Array<ResourceDetails>;
}

const AddCommentArea = ({
  resources,
  closeCommentArea
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { toIsoString } = useLocaleDateTimeFormat();
  const { showSuccessMessage } = useSnackbar();

  const [comment, setComment] = useState('');

  const success = (): void => {
    showSuccessMessage(t(labelYourCommentSent));
    closeCommentArea();
  };

  const { mutateAsync, isMutating, data } = useMutationQuery({
    getEndpoint: () => commentEndpoint,
    method: Method.POST
  });

  const changeComment = (event: ChangeEvent<HTMLInputElement>): void => {
    setComment(event?.target?.value);
  };

  const cancel = (): void => {
    setComment('');
    closeCommentArea();
  };

  const sendComment = (): void => {
    const date = toIsoString(new Date());

    const payload = [
      {
        ...resources.map(({ type, id, parent }) => ({
          id,
          parent: { id: parent?.id },
          type
        }))[0],
        comment,
        date
      }
    ];
    mutateAsync({
      resources: payload
    });
  };

  useEffect(() => {
    if (
      equals((data as ResponseError)?.isError, true) ||
      isNil((data as ResponseError)?.isError)
    ) {
      return;
    }

    success();
  }, [(data as ResponseError)?.isError]);

  return (
    <div className={classes.container}>
      <TextField
        autoFocus
        multiline
        required
        ariaLabel={t(labelComment)}
        autoComplete="off"
        className={classes.comment}
        dataTestId="addCommentFromTimeLine"
        label={t(labelComment)}
        rows={3}
        value={comment}
        onChange={changeComment}
      />
      <div className={classes.footer}>
        <Button size="small" variant="text" onClick={cancel}>
          {t(labelCancel)}
        </Button>
        <LoadingButton
          disabled={!comment}
          loading={isMutating}
          loadingIndicator="Loading…"
          size="small"
          variant="outlined"
          onClick={sendComment}
        >
          {t(labelSave)}
        </LoadingButton>
      </div>
    </div>
  );
};

export default AddCommentArea;
