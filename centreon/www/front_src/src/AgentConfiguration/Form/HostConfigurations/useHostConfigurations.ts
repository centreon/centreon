import { useFormikContext } from 'formik';
import { append, remove } from 'ramda';
import { useCallback } from 'react';
import {
  AgentConfigurationForm,
  CMAConfiguration,
  HostConfiguration
} from '../../models';

interface UseHostConfigurationsState {
  addHostConfiguration: () => void;
  deleteHostConfiguration: (index: number) => () => void;
  hosts: Array<HostConfiguration>;
}

export const useHostConfigurations = (): UseHostConfigurationsState => {
  const { values, setFieldValue } = useFormikContext<AgentConfigurationForm>();

  const { hosts } = values.configuration as CMAConfiguration;

  const addHostConfiguration = useCallback(() => {
    setFieldValue(
      'configuration.hosts',
      append(
        {
          address: '',
          port: '',
          certificate: '',
          key: ''
        },
        hosts
      )
    );
  }, [hosts]);

  const deleteHostConfiguration = useCallback(
    (index: number) => () => {
      setFieldValue('configuration.hosts', remove(index, 1, hosts));
    },
    [hosts]
  );

  return {
    addHostConfiguration,
    deleteHostConfiguration,
    hosts
  };
};
