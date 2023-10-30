import { isNil } from 'ramda';

import { InputPropsWithoutGroup } from './models';

const Custom = ({
  custom,
  ...props
}: InputPropsWithoutGroup): JSX.Element | null => {
  if (isNil(custom)) {
    return null;
  }

  return <custom.Component {...props} />;
};

export default Custom;
