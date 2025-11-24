import { ReactElement, useEffect, useMemo, useRef, useState } from 'react';

import SaveIon from '@mui/icons-material/Check';
import CloseIcon from '@mui/icons-material/Close';
import EditIcon from '@mui/icons-material/Edit';
import RestartIcon from '@mui/icons-material/RestartAlt';

import { equals, isNil, keys } from 'ramda';
import { useTranslation } from 'react-i18next';

import { IconButton, TextField } from '@centreon/ui';
import { Button, ItemComposition } from '@centreon/ui/components';
import {
  AdditionalConnectorConfiguration,
  ParameterKeys,
  Parameter as ParameterModel
} from '../../models';

import { useFormikContext } from 'formik';
import { useSearchParams } from 'react-router';
import {
  labelEditPassword,
  labelRevertToPreviousPassword
} from '../../translatedLabels';
import { maskedPassword } from '../../utils';
import useParameter from './useParameter';
import { useParameterStyles } from './useParametersStyles';

interface Props {
  index: number;
  parameter: ParameterModel;
}

enum PasswordActionState {
  Invisble = 'Invisble',
  Disabled = 'Disabled',
  Reset = 'Reset',
  Editing = 'Editing'
}

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

  if (equals(state, PasswordActionState.Invisble)) {
    return <div />;
  }

  if (equals(state, PasswordActionState.Disabled)) {
    return (
      <IconButton
        size="small"
        onClick={handleEdit}
        title={t(labelEditPassword)}
      >
        <EditIcon fontSize="small" />
      </IconButton>
    );
  }

  if (equals(state, PasswordActionState.Reset)) {
    return (
      <IconButton
        size="small"
        onClick={handleReset}
        title={t(labelRevertToPreviousPassword)}
      >
        <RestartIcon fontSize="small" />
      </IconButton>
    );
  }

  return (
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
        <SaveIon className="text-sm" />
      </Button>
    </div>
  );
};

const PasswordFiled = ({ error, index, onBlur, value }) => {
  const { t } = useTranslation();

  const passwordRef = useRef<HTMLInputElement>(null);

  const [state, setState] = useState(PasswordActionState.Disabled);

  const { setFieldValue } =
    useFormikContext<AdditionalConnectorConfiguration>();

  const changePasswordValue = (event): void => {
    setFieldValue(`parameters.vcenters.${index}.Password`, event.target.value);
  };

  const clearPassword = (): void => {
    setFieldValue(`parameters.vcenters.${index}.Password`, '');

    setTimeout(() => {
      passwordRef.current?.focus();
    }, 0);
  };

  const resetPassword = (): void => {
    setFieldValue(`parameters.vcenters.${index}.Password`, maskedPassword);
  };

  const [searchParams] = useSearchParams(window.location.search);
  const isEditMode = equals(searchParams.get('mode'), 'edit');

  const className = useMemo(
    () =>
      `flex ${equals(state, PasswordActionState.Editing) ? 'flex-column' : 'flex-row items-center'}`,
    [state]
  );

  useEffect(() => {
    if (isNil(value)) {
      setState(PasswordActionState.Invisble);
    }
  }, []);

  return (
    <div className={className}>
      <TextField
        inputRef={passwordRef}
        fullWidth
        dataTestId={'Password_value'}
        error={isEditMode ? null : error}
        label={t('Password')}
        name={'Password'}
        required={!isEditMode}
        type={
          !isEditMode || equals(state, PasswordActionState.Editing)
            ? 'text'
            : 'password'
        }
        value={value}
        onBlur={onBlur}
        onChange={changePasswordValue}
        disabled={
          isEditMode &&
          !equals(state, PasswordActionState.Editing) &&
          !equals(state, PasswordActionState.Invisble)
        }
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              autoComplete: 'off',
              readOnly: true,
              onFocus: (e) => e.target.removeAttribute('readonly')
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

const Parameter = ({ parameter, index }: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  const { changeParameterValue, getError, handleBlur } = useParameter({
    index
  });

  return (
    <ItemComposition addButtonHidden>
      <div
        className={classes.parameterComposition}
        data-testid="parameterGroup"
      >
        {keys(parameter).map((name) => (
          <div className={classes.parameterCompositionItem} key={name}>
            <ItemComposition.Item
              deleteButtonHidden
              className={classes.parameterItem}
              key={name}
            >
              {equals(name, ParameterKeys.password) ? (
                <PasswordFiled
                  index={index}
                  error={getError?.(name)}
                  onBlur={handleBlur(`parameters.vcenters.${index}.${name}`)}
                  value={parameter[name]}
                />
              ) : (
                <TextField
                  fullWidth
                  dataTestId={`${name}_value`}
                  error={getError?.(name)}
                  label={t(name)}
                  name={name}
                  required={true}
                  type="text"
                  value={parameter[name]}
                  onBlur={handleBlur(`parameters.vcenters.${index}.${name}`)}
                  onChange={changeParameterValue}
                />
              )}
            </ItemComposition.Item>
          </div>
        ))}
      </div>
    </ItemComposition>
  );
};

export default Parameter;
