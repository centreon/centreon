import { ReactElement } from 'react';

import SaveIcon from '@mui/icons-material/Check';
import CloseIcon from '@mui/icons-material/Close';
import EditIcon from '@mui/icons-material/Edit';
import RestartIcon from '@mui/icons-material/RestartAlt';

import { T, always, cond, equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { IconButton } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { PasswordActionState } from '../../../models';
import {
  labelEditPassword,
  labelRevertToPreviousPassword
} from '../../../translatedLabels';

const PasswordIcon = ({
  state,
  setState,
  isEmpty,
  clearPassword,
  resetPassword
}): ReactElement => {
  const { t } = useTranslation();

  const changePasswordState =
    (passwordState: PasswordActionState) => (): void => {
      setState(passwordState);
    };

  const handleEdit = (): void => {
    setState(PasswordActionState.Editing);
    clearPassword();
  };

  const handleReset = (): void => {
    setState(PasswordActionState.Disabled);
    resetPassword();
  };

  const result = cond([
    [equals(PasswordActionState.Invisble), always(<div />)],
    [
      equals(PasswordActionState.Disabled),
      always(
        <IconButton
          size="small"
          onClick={handleEdit}
          title={t(labelEditPassword)}
        >
          <EditIcon fontSize="small" />
        </IconButton>
      )
    ],
    [
      equals(PasswordActionState.Reset),
      always(
        <IconButton
          size="small"
          onClick={handleReset}
          title={t(labelRevertToPreviousPassword)}
        >
          <RestartIcon fontSize="small" />
        </IconButton>
      )
    ],
    [
      T,
      always(
        <div className="flex gap-1 justify-end">
          <Button
            size="small"
            variant="secondary"
            onClick={handleReset}
            className="min-w-[40px]"
          >
            <CloseIcon className="text-sm" />
          </Button>
          <Button
            variant="secondary"
            size="small"
            onClick={changePasswordState(PasswordActionState.Reset)}
            disabled={isEmpty}
            className="min-w-[40px]"
          >
            <SaveIcon className="text-sm" />
          </Button>
        </div>
      )
    ]
  ]);

  return result(state);
};

export default PasswordIcon;
