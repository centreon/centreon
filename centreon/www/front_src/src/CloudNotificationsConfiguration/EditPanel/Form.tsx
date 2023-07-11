import { useAtomValue } from 'jotai';

import { Box } from '@mui/material';

import { Form as FormComponent } from '@centreon/ui';

import { panelWidthStorageAtom } from '../atom';

import useStyles from './Form.styles';
import useFormInputs from './FormInputs/useFormInputs';
import useValidationSchema from './validationSchema';
import ReducePanel from './ReducePanel';
import { Header } from './Header';
import useFormSubmit from './useFormSubmit';
import useFormInitialValues from './useFormInitialValues';

const Form = (): JSX.Element => {
  const { classes } = useStyles();

  const panelWidth = useAtomValue(panelWidthStorageAtom);

  const { initialValues, isLoading } = useFormInitialValues();
  const { submit } = useFormSubmit();

  const { inputs, basicFormGroups } = useFormInputs({ panelWidth });
  const { validationSchema } = useValidationSchema();

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
