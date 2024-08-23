import { useTranslation } from 'react-i18next';

import { CircularProgress } from '@mui/material';

import { Button } from '../../..';
import { AccessRightInitialValues, Labels } from '../models';

import { useActionsStyles } from './Actions.styles';
import { useActions } from './useActions';

interface Props {
  cancel?: ({ dirty, values }) => void;
  clear: () => void;
  isSubmitting?: boolean;
  labels: Labels['actions'];
  submit: (values: Array<AccessRightInitialValues>) => Promise<void>;
}

const Actions = ({
  labels,
  cancel,
  submit,
  isSubmitting,
  clear
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { dirty, save, formattedValues } = useActions({
    clear,
    labels,
    submit
  });

  const onCancel = (): void => {
    cancel({ dirty, values: formattedValues });
  };

  return (
    <div className={classes.cancelAndSave}>
      {cancel && (
        <Button
          aria-label={t(labels.cancel)}
          variant="secondary"
          onClick={onCancel}
        >
          {t(labels.cancel)}
        </Button>
      )}
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
  );
};

export default Actions;
