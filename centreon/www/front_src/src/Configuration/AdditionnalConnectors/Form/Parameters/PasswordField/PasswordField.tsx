import { TextField } from '@centreon/ui';

import { FocusEventHandler, ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import PasswordIcon from './PasswordIcon';
import { usePasswordField } from './usePasswordField';

interface Props {
  error?: string;
  index: number;
  onBlur: FocusEventHandler<HTMLInputElement | HTMLTextAreaElement>;
  value: string;
}

const PasswordFiled = ({
  error,
  index,
  onBlur,
  value
}: Props): ReactElement => {
  const { t } = useTranslation();

  const {
    disabled,
    className,
    changePasswordValue,
    clearPassword,
    resetPassword,
    passwordRef,
    state,
    setState,
    isEditMode
  } = usePasswordField({ index, value });

  return (
    <div className={className}>
      <TextField
        dataTestId="Password_value"
        disabled={disabled}
        error={isEditMode ? undefined : error}
        fullWidth
        inputRef={passwordRef}
        label={t('Password')}
        name={'Password'}
        onBlur={onBlur}
        onChange={changePasswordValue}
        required={!isEditMode}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              autoComplete: 'off'
            }
          }
        }}
        type="password"
        value={value}
      />
      {isEditMode && (
        <PasswordIcon
          clearPassword={clearPassword}
          isEmpty={!value}
          resetPassword={resetPassword}
          setState={setState}
          state={state}
        />
      )}
    </div>
  );
};

export default PasswordFiled;
