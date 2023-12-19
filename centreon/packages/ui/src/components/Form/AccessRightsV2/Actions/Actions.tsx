import { useTranslation } from 'react-i18next';

import LinkIcon from '@mui/icons-material/Link';

import { Button } from '../../..';
import { AccessRightInitialValues, Labels } from '../models';

import { useActions } from './useActions';
import { useActionsStyles } from './Actions.styles';

interface Props {
  cancel: (dirty: boolean) => void;
  labels: Labels['actions'];
  link?: string;
  submit: (values: Array<AccessRightInitialValues>) => void;
}

const Actions = ({ labels, cancel, submit, link }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { dirty, copyLink, save } = useActions({ labels, link, submit });

  const onCancel = (): void => {
    cancel(dirty)
  }

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
          disabled={!dirty}
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
