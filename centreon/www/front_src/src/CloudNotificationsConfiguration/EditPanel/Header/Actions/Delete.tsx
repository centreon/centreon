import { useAtomValue, useSetAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';
import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { labelDeleteNotification } from '../../../translatedLabels';
import { editedNotificationIdAtom } from '../../atom';
import { DeleteButton } from '../../../Actions';
import { deleteNotificationAtom, isDeleteDialogOpenAtom } from '../../../atom';
import { DeleteType } from '../../../models';

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
  const setDeleteInformations = useSetAtom(deleteNotificationAtom);
  const setIsDeleteDialog = useSetAtom(isDeleteDialogOpenAtom);

  const {
    values: { name }
  } = useFormikContext<FormikValues>();

  const onClick = (): void => {
    setDeleteInformations({
      id: notificationId as number,
      name,
      type: DeleteType.SingleItem
    });
    setIsDeleteDialog(true);
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
