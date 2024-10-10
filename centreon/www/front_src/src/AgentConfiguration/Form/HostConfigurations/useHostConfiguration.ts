import { SelectEntry } from '@centreon/ui';
import { useFormikContext } from 'formik';
import { isEmpty, isNil } from 'ramda';
import { ChangeEvent, useCallback, useMemo } from 'react';
import { AgentConfigurationForm, HostConfiguration } from '../../models';
import { portRegex } from '../useValidationSchema';

interface UseHostConfigurationProps {
  index: number;
}

interface UseHostConfigurationState {
  selectHost: (_, entry: SelectEntry & { address: string }) => void;
  changeAddress: (event: ChangeEvent<HTMLInputElement>) => void;
  changePort: (newValue: number) => void;
  changeStringInput: (
    property: string
  ) => (event: ChangeEvent<HTMLInputElement>) => void;
  hostErrors: Partial<HostConfiguration> | undefined;
  hostTouched: Partial<HostConfiguration> | undefined;
}

export const useHostConfiguration = ({
  index
}: UseHostConfigurationProps): UseHostConfigurationState => {
  const { setFieldValue, setFieldTouched, errors, touched, setFieldError } =
    useFormikContext<AgentConfigurationForm>();

  const selectHost = useCallback(
    (_, { address }) => {
      setFieldTouched(`configuration.hosts.${index}.address`, true, false);
      setFieldTouched(`configuration.hosts.${index}.port`, true, false);
      setFieldValue(`configuration.hosts.${index}.address`, address, false);
      setFieldValue(`configuration.hosts.${index}.port`, 4317, false);
      setFieldError(`configuration.hosts.${index}.address`, undefined);
      setFieldError(`configuration.hosts.${index}.port`, undefined);
    },
    [index]
  );

  const changeAddress = useCallback(
    (event: ChangeEvent<HTMLInputElement>) => {
      const { value } = event.target;
      const port = value.match(portRegex);

      if (isNil(port) || isEmpty(port)) {
        setFieldTouched(`configuration.hosts.${index}.address`, true, false);
        setFieldValue(`configuration.hosts.${index}.address`, value);
        return;
      }

      const newAddress = value.replace(port[0], '');

      setFieldTouched(`configuration.hosts.${index}.address`, true, false);
      setFieldTouched(`configuration.hosts.${index}.port`, true, false);
      setFieldValue(`configuration.hosts.${index}.address`, newAddress, false);
      setFieldValue(
        `configuration.hosts.${index}.port`,
        port[0].substring(1),
        false
      );
      setFieldError(`configuration.hosts.${index}.address`, undefined);
      setFieldError(`configuration.hosts.${index}.port`, undefined);
    },
    [index]
  );

  const changePort = useCallback((newValue: number) => {
    setFieldTouched(`configuration.hosts.${index}.port`, true, false);
    setFieldValue(`configuration.hosts.${index}.port`, newValue);
  }, []);

  const changeStringInput = useCallback(
    (property: string) => (event: ChangeEvent<HTMLInputElement>) => {
      setFieldTouched(`configuration.hosts.${index}.${property}`, true, false);
      setFieldValue(
        `configuration.hosts.${index}.${property}`,
        event.target.value
      );
    },
    []
  );

  const hostErrors = useMemo(
    () => errors.configuration?.hosts?.[index],
    [errors, index]
  );

  const hostTouched = useMemo(
    () => touched.configuration?.hosts?.[index],
    [touched, index]
  );

  return {
    changeAddress,
    changePort,
    changeStringInput,
    selectHost,
    hostErrors,
    hostTouched
  };
};
