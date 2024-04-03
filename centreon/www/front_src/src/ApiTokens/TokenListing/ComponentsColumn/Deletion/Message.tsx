import DOMPurify from 'dompurify';
import parse from 'html-react-parser';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { Typography } from '@mui/material';

import { labelMsgConfirmationDeletionToken } from '../../../translatedLabels';
import { clickedRowAtom } from '../../atoms';

import { useStyles } from './deletion.styles';

const Message = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const clickedRow = useAtomValue(clickedRowAtom);

  return (
    <Typography className={classes.labelMessage}>
      {parse(
        DOMPurify.sanitize(
          t(labelMsgConfirmationDeletionToken, { tokenName: clickedRow?.name })
        )
      )}
    </Typography>
  );
};

export default Message;
