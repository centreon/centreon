// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Form } from '@centreon/ui';

import { useMemo } from 'react';

import { useGetVaultConfiguration } from '../hooks/useGetVaultConfiguration';
import { useUpdateVaultConfiguration } from '../hooks/useUpdateVaultConfiguration';
import { PostVaultConfiguration } from '../models';
import Buttons from './Buttons';
import Skeletons from './Skeletons';
import { useFormStyles } from './useFormStyles';
import { useInputs } from './useInputs';
import { useValidationSchema } from './useValidationSchema';

const VaultForm = (): JSX.Element => {
  const { classes } = useFormStyles();

  const { data, isLoading } = useGetVaultConfiguration();

  const submitVaultConfiguration = useUpdateVaultConfiguration();

  const validationSchema = useValidationSchema();
  const inputs = useInputs();

  // The form reinitializes whenever these change, so they follow the loaded
  // configuration rather than each render. It has no role or secret ID.
  const initialValues = useMemo(
    () => ({
      address: data?.address,
      port: data?.port,
      roleId: '',
      rootPath: data?.rootPath,
      secretId: ''
    }),
    [data]
  );

  if (isLoading && !data) {
    return <Skeletons />;
  }

  return (
    <Form<PostVaultConfiguration>
      Buttons={Buttons}
      className={classes.group}
      initialValues={initialValues}
      inputs={inputs}
      submit={submitVaultConfiguration}
      validationSchema={validationSchema}
    />
  );
};

export default VaultForm;
