import { FormikValues, useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { DeleteButton, useDelete } from '../../../Actions/Delete';
import { DeleteType } from '../../../models';
import { labelDeleteNotification } from '../../../translatedLabels';
import { editedNotificationIdAtom } from '../../atom';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.5)
  }
}));

const DeleteAction = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyle();

  const notificationId = useAtomValue(editedNotificationIdAtom);
  const { deleteItems } = useDelete();

  const {
    values: { name }
  } = useFormikContext<FormikValues>();

  const onClick = (): void => {
    deleteItems({
      id: notificationId as number,
      name,
      type: DeleteType.SingleItem
    });
  };

  return (
    <DeleteButton
      ariaLabel={t(labelDeleteNotification) as string}
      iconClassName={classes.icon}
      onClick={onClick}
    />
  );
};

export default DeleteAction;
