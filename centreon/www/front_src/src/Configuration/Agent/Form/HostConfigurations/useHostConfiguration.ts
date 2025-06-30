import { ChangeEvent, useCallback, useMemo } from 'react';

import { SelectEntry } from '@centreon/ui';
import { useFormikContext } from 'formik';
import { equals, isEmpty, isNil } from 'ramda';
import { portRegex } from '../useValidationSchema';

import {
  AgentConfigurationForm,
  ConnectionMode,
  HostConfiguration
} from '../../models';

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
  areCertificateFieldsVisible: boolean;
  changeCMAToken: (_, tokens: Array<SelectEntry>) => void;
  token: { id: string; name: string };
}

export const useHostConfiguration = ({
  index
}: UseHostConfigurationProps): UseHostConfigurationState => {
  const {
    setFieldValue,
    setFieldTouched,
    errors,
    touched,
    setFieldError,
    values
  } = useFormikContext<AgentConfigurationForm>();

  const selectHost = useCallback(
    (_, { id, name, address }) => {
      setFieldTouched(`configuration.hosts.${index}.address`, true, false);
      setFieldTouched(`configuration.hosts.${index}.port`, true, false);

      setFieldValue(`configuration.hosts.${index}.name`, name, false);
      setFieldValue(`configuration.hosts.${index}.id`, id, false);
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
        setFieldTouched(`configuration.hosts.${index}.address`, true);
        setFieldValue(`configuration.hosts.${index}.address`, value);
        return;
      }

      const newAddress = value.replace(port[0], '');
      setFieldTouched(`configuration.hosts.${index}.address`, true, false);
      setFieldTouched(`configuration.hosts.${index}.port`, true, false);
      setFieldError(`configuration.hosts.${index}.address`, undefined);
      setFieldError(`configuration.hosts.${index}.port`, undefined);
      setFieldValue(`configuration.hosts.${index}`, {
        ...values.configuration.hosts[index],
        address: newAddress,
        port: port[0].substring(1)
      });
    },
    [index, values]
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

  const token = useMemo(
    () => values.configuration?.hosts[index].token,
    [values.configuration]
  );

  const changeCMAToken = (_, token: Array<SelectEntry>): void => {
    setFieldValue(`configuration.hosts.${index}.token`, token);
  };

  const hostErrors = useMemo(
    () => errors.configuration?.hosts?.[index],
    [errors, index]
  );

  const hostTouched = useMemo(
    () => touched.configuration?.hosts?.[index],
    [touched, index]
  );

  const areCertificateFieldsVisible =
    equals(values?.connectionMode?.id, ConnectionMode.secure) ||
    equals(values?.connectionMode?.id, ConnectionMode.insecure);

  return {
    changeAddress,
    changePort,
    changeStringInput,
    selectHost,
    hostErrors,
    hostTouched,
    areCertificateFieldsVisible,
    changeCMAToken,
    token
  };
};
