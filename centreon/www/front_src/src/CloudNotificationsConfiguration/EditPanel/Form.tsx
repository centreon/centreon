import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Box } from '@mui/material';

import { Form as FormComponent, useFetchQuery } from '@centreon/ui';

import { panelWidthStorageAtom } from '../atom';

import useStyles from './Form.styles';
import useFormInputs from './FormInputs/useFormInputs';
import { emptyInitialValues, getInitialValues } from './initialValues';
import useValidationSchema from './validationSchema';
import { EditedNotificationIdAtom, panelModeAtom } from './atom';
import { PanelMode } from './models';
import { notificationtEndpoint } from './api/endpoints';
import { notificationdecoder } from './api/decoders';
import ReducePanel from './ReducePanel';
import Header from './Header';

const Form = (): JSX.Element => {
  const { classes } = useStyles();

  const panelMode = useAtomValue(panelModeAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);

  const { inputs, basicFormGroups } = useFormInputs({ panelWidth });
  const { validationSchema } = useValidationSchema();

  const { data, isLoading } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationtEndpoint({ id: editedNotificationId }),
    getQueryKey: () => ['notification', editedNotificationId],
    queryOptions: {
      enabled: equals(panelMode, PanelMode.Edit),
      suspense: false
    }
  });

  const initialValues =
    equals(panelMode, PanelMode.Edit) && data
      ? getInitialValues(data)
      : emptyInitialValues;

  const loading = equals(panelMode, PanelMode.Edit) ? isLoading : false;

  return (
    <Box>
      <FormComponent
        areGroupsOpen
        isCollapsible
        Buttons={Box}
        className={classes.form}
        groups={basicFormGroups}
        groupsClassName={classes.groups}
        initialValues={initialValues}
        inputs={inputs}
        isLoading={loading}
        validationSchema={validationSchema}
      >
        <>
          <Header />
          <ReducePanel />
        </>
      </FormComponent>
    </Box>
  );
};

export default Form;
