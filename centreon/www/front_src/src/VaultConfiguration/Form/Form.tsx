import { Form } from '@centreon/ui';

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

  if (isLoading && !data) {
    return <Skeletons />;
  }

  return (
    <Form<PostVaultConfiguration>
      Buttons={Buttons}
      className={classes.group}
      initialValues={{
        address: data?.address,
        port: data?.port,
        roleId: '',
        rootPath: data?.rootPath,
        secretId: ''
      }}
      inputs={inputs}
      submit={submitVaultConfiguration}
      validationSchema={validationSchema}
    />
  );
};

export default VaultForm;
