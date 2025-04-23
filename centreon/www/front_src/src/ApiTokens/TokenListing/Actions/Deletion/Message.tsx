import DOMPurify from 'dompurify';
import parse from 'html-react-parser';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { labelMsgConfirmationDeletionToken } from '../../../translatedLabels';
import { selectedRowAtom } from '../../atoms';

import { useStyles } from './deletion.styles';

const Message = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const selectedRow = useAtomValue(selectedRowAtom);

  return (
    <span className={classes.labelMessage}>
      {parse(
        DOMPurify.sanitize(
          t(labelMsgConfirmationDeletionToken, { tokenName: selectedRow?.name })
        )
      )}
    </span>
  );
};

export default Message;
