import DOMPurify from 'dompurify';
import parse from 'html-react-parser';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelMsgConfirmationDeletionToken } from '../../../translatedLabels';

import { useStyles } from './deletion.styles';

const Message = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Typography className={classes.labelMessage}>
      {parse(DOMPurify.sanitize(t(labelMsgConfirmationDeletionToken)))}
    </Typography>
  );
};

export default Message;
