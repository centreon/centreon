import SaveIcon from '@mui/icons-material/Check';
import CloseIcon from '@mui/icons-material/Close';
import EditIcon from '@mui/icons-material/Edit';
import RestartIcon from '@mui/icons-material/RestartAlt';

import { IconButton } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { always, cond, equals, T } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

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
          dataTestid={'button_edit'}
          onClick={handleEdit}
          size="small"
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
          dataTestid={'button_reset'}
          onClick={handleReset}
          size="small"
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
            className="min-w-[40px]"
            data-testid={'button_cancel'}
            onClick={handleReset}
            size="small"
            variant="secondary"
          >
            <CloseIcon className="text-sm" />
          </Button>
          <Button
            className="min-w-[40px]"
            data-testid={'button_save'}
            disabled={isEmpty}
            onClick={changePasswordState(PasswordActionState.Reset)}
            size="small"
            variant="secondary"
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
