import { useAtomValue } from 'jotai';

import { Box } from '@mui/material';

import { Form as FormComponent } from '@centreon/ui';

import { panelWidthStorageAtom } from '../../atom';
import useFormInitialValues from '../FormInitialValues/useFormInitialValues';
import useFormInputs from '../FormInputs/useFormInputs';
import { Header } from '../Header';
import ReducePanel from '../ReducePanel';
import useIsBamModuleInstalled from '../useIsBamModuleInstalled';

import useStyles from './Form.styles';
import useFormSubmit from './useFormSubmit';
import useValidationSchema from './useValidationSchema';

const Form = (): JSX.Element => {
  const { classes } = useStyles();

  const panelWidth = useAtomValue(panelWidthStorageAtom);

  const { submit } = useFormSubmit();
  const isBamModuleInstalled = useIsBamModuleInstalled();

  const { initialValues, isLoading } = useFormInitialValues({
    isBamModuleInstalled
  });

  const { inputs, basicFormGroups } = useFormInputs({
    isBamModuleInstalled,
    panelWidth
  });
  const { validationSchema } = useValidationSchema({ isBamModuleInstalled });

  return (
    <FormComponent
      areGroupsOpen
      isCollapsible
      Buttons={Box}
      className={classes.form}
      groups={basicFormGroups}
      groupsClassName={classes.groups}
      initialValues={initialValues}
      inputs={inputs}
      isLoading={isLoading}
      submit={submit}
      validationSchema={validationSchema}
    >
      <>
        <Header />
        <ReducePanel />
      </>
    </FormComponent>
  );
};

export default Form;
