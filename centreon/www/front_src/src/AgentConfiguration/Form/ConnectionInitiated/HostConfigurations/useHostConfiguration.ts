import { SelectEntry } from '@centreon/ui';

import { FormikErrors, FormikTouched, useFormikContext } from 'formik';
import { equals, isEmpty, isNil } from 'ramda';
import { ChangeEvent, useCallback, useMemo } from 'react';

import {
  AgentConfigurationForm,
  CMAConfiguration,
  ConnectionMode,
  HostConfiguration
} from '../../../models';
import { portRegex } from '../../useValidationSchema';

interface UseHostConfigurationProps {
  index: number;
}

interface UseHostConfigurationState {
  selectHost: (_: unknown, entry: SelectEntry & { address: string }) => void;
  changeAddress: (event: ChangeEvent<HTMLInputElement>) => void;
  changePort: (newValue: number) => void;
  changeStringInput: (
    property: string
  ) => (event: ChangeEvent<HTMLInputElement>) => void;
  hostErrors: FormikErrors<HostConfiguration> | undefined;
  hostTouched: FormikTouched<HostConfiguration> | undefined;
  isInsecureMode: boolean;
  isSecureMode: boolean;
  changeCMAToken: (_: unknown, tokens: Array<SelectEntry>) => void;
  token: { id: string; name: string } | undefined | null;
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
    values,
    validateForm
  } = useFormikContext<AgentConfigurationForm>();

  const selectHost = useCallback(
    (_: unknown, { id, name, address }: SelectEntry & { address: string }) => {
      setFieldTouched(`configuration.hosts.${index}.address`, true, false);
      setFieldTouched(`configuration.hosts.${index}.port`, true, false);

      setFieldValue(`configuration.hosts.${index}.name`, name, false);
      setFieldValue(`configuration.hosts.${index}.id`, id, false);
      setFieldValue(`configuration.hosts.${index}.address`, address, false);
      setFieldValue(`configuration.hosts.${index}.port`, 4317, false);

      setFieldError(`configuration.hosts.${index}.address`, undefined);
      setFieldError(`configuration.hosts.${index}.port`, undefined);

      setTimeout(() => {
        validateForm();
      }, 0);
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

      const cmaConf = values.configuration as CMAConfiguration;
      const newAddress = value.replace(port[0], '');
      setFieldTouched(`configuration.hosts.${index}.address`, true, false);
      setFieldTouched(`configuration.hosts.${index}.port`, true, false);
      setFieldError(`configuration.hosts.${index}.address`, undefined);
      setFieldError(`configuration.hosts.${index}.port`, undefined);
      setFieldValue(`configuration.hosts.${index}`, {
        ...cmaConf.hosts[index],
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
    () => (values.configuration as CMAConfiguration)?.hosts?.[index]?.token,
    [values.configuration]
  );

  const changeCMAToken = (_: unknown, token: Array<SelectEntry>): void => {
    setFieldValue(`configuration.hosts.${index}.token`, token);
  };

  const hostErrors = useMemo(
    () =>
      (errors.configuration as FormikErrors<CMAConfiguration>)?.hosts?.[
        index
      ] as FormikErrors<HostConfiguration> | undefined,
    [errors, index]
  );

  const hostTouched = useMemo(
    () =>
      (touched.configuration as FormikTouched<CMAConfiguration>)?.hosts?.[
        index
      ] as FormikTouched<HostConfiguration> | undefined,
    [touched, index]
  );

  const isInsecureMode = equals(
    values?.connectionMode?.id,
    ConnectionMode.insecure
  );
  const isSecureMode = equals(
    values?.connectionMode?.id,
    ConnectionMode.secure
  );

  return {
    changeAddress,
    changeCMAToken,
    changePort,
    changeStringInput,
    hostErrors,
    hostTouched,
    isInsecureMode,
    isSecureMode,
    selectHost,
    token
  };
};
