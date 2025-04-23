import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { DuplicateButton, useDuplicate } from '../../../Actions/Duplicate';
import { labelDuplicate } from '../../../translatedLabels';

const useStyle = makeStyles<{ disabled: boolean }>()((theme, { disabled }) => ({
  icon: {
    color: disabled
      ? theme.palette.action.disabled
      : theme.palette.text.secondary,
    fontSize: theme.spacing(2)
  }
}));

const DuplicateAction = (): JSX.Element => {
  const { initialValues, dirty } = useFormikContext<FormikValues>();
  const { classes } = useStyle({ disabled: dirty });
  const { t } = useTranslation();
  const { duplicateItem } = useDuplicate();

  const onClick = (): void => {
    duplicateItem({ id: initialValues.id, notification: initialValues });
  };

  return (
    <DuplicateButton
      ariaLabel={t(labelDuplicate) as string}
      className={classes.icon}
      disabled={dirty}
      onClick={onClick}
    />
  );
};

export default DuplicateAction;
