import type { InputPropsWithoutGroup } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { type FormikValues, useFormikContext } from 'formik';
import { type ReactElement, useEffect, useRef } from 'react';

// Not through `../api`, whose decoders import the sections rendering this.
import useResolveAddress from '../api/useResolveAddress';

// An IPv4 comes back unchanged, so there is nothing to resolve.
const ipv4 = /^\d{1,3}(\.\d{1,3}){3}$/;

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
  const currentAddress = useRef(hostname);

  useEffect(() => {
    currentAddress.current = hostname;
  }, [hostname]);

  const resolveAddress = async (): Promise<void> => {
    const ip = await resolve(hostname);

    // The address may have been edited while the name was resolving.
    if (!ip || currentAddress.current !== hostname) {
      return;
    }

    await setFieldValue('address', ip);
    setFieldTouched('address', true);
  };

  return (
    <Button
      data-testid={dataTestId}
      disabled={
        !hostname ||
        ipv4.test(hostname) ||
        isResolving ||
        !!getDisabled?.(values)
      }
      onClick={resolveAddress}
      variant="primary"
    >
      {label}
    </Button>
  );
};

export default ResolveAddress;
