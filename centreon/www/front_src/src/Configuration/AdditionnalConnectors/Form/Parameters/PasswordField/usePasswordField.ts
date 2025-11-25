import {
  Dispatch,
  RefObject,
  SetStateAction,
  useEffect,
  useMemo,
  useRef,
  useState
} from 'react';

import { useFormikContext } from 'formik';
import { equals, isNil } from 'ramda';
import { useSearchParams } from 'react-router';

import {
  AdditionalConnectorConfiguration,
  PasswordActionState
} from '../../../models';

import { maskedPassword } from '../../../utils';

interface UsePasswordFieldState {
  disabled: boolean;
  type: 'text' | 'password';
  className: string;
  changePasswordValue: (event) => void;
  clearPassword: () => void;
  resetPassword: () => void;
  passwordRef: RefObject<HTMLInputElement | null>;
  state: PasswordActionState;
  setState: Dispatch<SetStateAction<PasswordActionState>>;
  isEditMode: boolean;
}

interface UsePasswordFieldProps {
  index: number;
  value: string;
}

export const usePasswordField = ({
  index,
  value
}: UsePasswordFieldProps): UsePasswordFieldState => {
  const passwordRef = useRef<HTMLInputElement>(null);

  const [state, setState] = useState(PasswordActionState.Disabled);
  const [searchParams] = useSearchParams(window.location.search);

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

  const isEditMode = equals(searchParams.get('mode'), 'edit');

  const className = useMemo(
    () =>
      `flex ${equals(state, PasswordActionState.Editing) ? 'flex-column' : 'flex-row items-center'}`,
    [state]
  );

  const disabled =
    isEditMode &&
    !equals(state, PasswordActionState.Editing) &&
    !equals(state, PasswordActionState.Invisble);

  const type =
    isEditMode && equals(state, PasswordActionState.Editing)
      ? 'text'
      : 'password';

  useEffect(() => {
    if (isNil(value)) {
      setState(PasswordActionState.Invisble);
    }
  }, []);

  return {
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
  };
};
