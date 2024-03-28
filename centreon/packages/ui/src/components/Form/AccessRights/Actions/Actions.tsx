import { useTranslation } from 'react-i18next';

import LinkIcon from '@mui/icons-material/Link';
import { CircularProgress } from '@mui/material';

import { Button } from '../../..';
import { AccessRightInitialValues, Labels } from '../models';

import { useActions } from './useActions';
import { useActionsStyles } from './Actions.styles';

interface Props {
  cancel: ({ dirty, values }) => void;
  clear: () => void;
  isSubmitting?: boolean;
  labels: Labels['actions'];
  link?: string;
  submit: (values: Array<AccessRightInitialValues>) => Promise<void>;
}

const Actions = ({
  labels,
  cancel,
  submit,
  link,
  isSubmitting,
  clear
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { dirty, copyLink, save, formattedValues } = useActions({
    clear,
    labels,
    link,
    submit
  });

  const onCancel = (): void => {
    cancel({ dirty, values: formattedValues });
  };

  return (
    <div className={classes.actions}>
      {link ? (
        <Button
          aria-label={t(labels.copyLink)}
          icon={<LinkIcon />}
          iconVariant="start"
          variant="ghost"
          onClick={copyLink}
        >
          {t(labels.copyLink)}
        </Button>
      ) : (
        <div />
      )}
      <div className={classes.cancelAndSave}>
        <Button
          aria-label={t(labels.cancel)}
          variant="secondary"
          onClick={onCancel}
        >
          {t(labels.cancel)}
        </Button>
        <Button
          aria-label={t(labels.save)}
          disabled={isSubmitting || !dirty}
          icon={isSubmitting ? <CircularProgress size={24} /> : null}
          iconVariant={isSubmitting ? 'start' : 'none'}
          variant="primary"
          onClick={save}
        >
          {t(labels.save)}
        </Button>
      </div>
    </div>
  );
};

export default Actions;
