import { ReactNode } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { isNil } from 'ramda';

import { ShowInput } from '../models';

import { showInput } from './Inputs/utils';

interface ShowInputWrapperProps {
  children: ReactNode;
  show?: ShowInput;
}

const ShowInputWrapper = ({
  children,
  show
}: ShowInputWrapperProps): ReactNode | null => {
  const { values } = useFormikContext<FormikValues>();

  if (isNil(show)) {
    return children;
  }

  const shouldShowInput = showInput({ ...show, values });

  if (!shouldShowInput) {
    return null;
  }

  return children;
};

export default ShowInputWrapper;
