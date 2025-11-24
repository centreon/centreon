import { FocusEventHandler, ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { TextField } from '@centreon/ui';

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
    type,
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
        inputRef={passwordRef}
        fullWidth
        dataTestId={'Password_value'}
        error={isEditMode ? undefined : error}
        label={t('Password')}
        name={'Password'}
        required={!isEditMode}
        type={type}
        value={value}
        onBlur={onBlur}
        onChange={changePasswordValue}
        disabled={disabled}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              autoComplete: 'off'
            }
          }
        }}
      />
      {isEditMode && (
        <PasswordIcon
          state={state}
          setState={setState}
          clearPassword={clearPassword}
          resetPassword={resetPassword}
          isEmpty={!value}
        />
      )}
    </div>
  );
};

export default PasswordFiled;
