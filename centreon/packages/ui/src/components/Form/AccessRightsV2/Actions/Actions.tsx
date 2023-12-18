import { useTranslation } from 'react-i18next';

import LinkIcon from '@mui/icons-material/Link';

import { Button } from '../../..';
import { AccessRightInitialValues, Labels } from '../models';

import { useActions } from './useActions';
import { useActionsStyles } from './Actions.styles';

interface Props {
  cancel: () => void;
  labels: Labels['actions'];
  link?: string;
  submit: (values: Array<AccessRightInitialValues>) => void;
}

const Actions = ({ labels, cancel, submit, link }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { dirty, copyLink, save } = useActions({ labels, link, submit });

  return (
    <div className={classes.actions}>
      {link ? (
        <Button
          icon={<LinkIcon />}
          iconVariant="start"
          variant="ghost"
          onClick={copyLink}
          aria-label={t(labels.copyLink)}
        >
          {t(labels.copyLink)}
        </Button>
      ) : (
        <div />
      )}
      <div className={classes.cancelAndSave}>
        <Button variant="secondary" onClick={cancel} aria-label={t(labels.cancel)}>
          {t(labels.cancel)}
        </Button>
        <Button disabled={!dirty} variant="primary" onClick={save} aria-label={t(labels.save)}>
          {t(labels.save)}
        </Button>
      </div>
    </div>
  );
};

export default Actions;
