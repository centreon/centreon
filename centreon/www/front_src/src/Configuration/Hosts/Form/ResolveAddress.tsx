import type { InputPropsWithoutGroup } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { type FormikValues, useFormikContext } from 'formik';
import type { ReactElement } from 'react';

// Not through `../api`, whose decoders import the sections rendering this.
import useResolveAddress from '../api/useResolveAddress';

// Replaces the address with the IPv4 its name resolves to.
const ResolveAddress = ({
  dataTestId,
  getDisabled,
  label
}: InputPropsWithoutGroup): ReactElement => {
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<FormikValues>();
  const { isResolving, resolve } = useResolveAddress();

  const hostname = ((values.address as string | undefined) ?? '').trim();

  const resolveAddress = async (): Promise<void> => {
    const ip = await resolve(hostname);

    if (!ip) {
      return;
    }

    await setFieldValue('address', ip);
    setFieldTouched('address', true);
  };

  return (
    <Button
      data-testid={dataTestId}
      disabled={!hostname || isResolving || !!getDisabled?.(values)}
      onClick={resolveAddress}
      variant="primary"
    >
      {label}
    </Button>
  );
};

export default ResolveAddress;
