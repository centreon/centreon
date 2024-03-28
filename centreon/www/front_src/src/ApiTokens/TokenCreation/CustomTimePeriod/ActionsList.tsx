import { useTranslation } from 'react-i18next';

import { PickersActionBarProps } from '@mui/x-date-pickers/PickersActionBar';

import { SaveButton as Button } from '@centreon/ui';

import { labelCancel, labelOk } from '../../translatedLabels';
import { useStyles } from '../tokenCreation.styles';

interface ActionListProps {
  acceptDate: () => void;
  cancelDate: () => void;
  isInvalidDate: boolean;
}

const ActionList = (
  props: PickersActionBarProps & ActionListProps
): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { onCancel, className, acceptDate, cancelDate, isInvalidDate } = props;

  const cancel = (): void => {
    onCancel();
    cancelDate();
  };

  return (
    <div className={cx(className, classes.container)}>
      <Button
        labelSave={t(labelCancel)}
        startIcon={false}
        variant="text"
        onClick={cancel}
      />
      <Button
        disabled={isInvalidDate}
        labelSave={t(labelOk)}
        startIcon={false}
        variant="text"
        onClick={acceptDate}
      />
    </div>
  );
};

export default ActionList;
