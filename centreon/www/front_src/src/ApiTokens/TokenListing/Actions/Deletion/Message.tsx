import DOMPurify from 'dompurify';
import parse from 'html-react-parser';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelMsgConfirmationDeletionToken } from '../../../translatedLabels';
import { selectedRowAtom } from '../../atoms';

import { useStyles } from './deletion.styles';

const Message = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const selectedRow = useAtomValue(selectedRowAtom);

  return (
    <Typography className={classes.labelMessage}>
      {parse(
        DOMPurify.sanitize(
          t(labelMsgConfirmationDeletionToken, { tokenName: selectedRow?.name })
        )
      )}
    </Typography>
  );
};

export default Message;
