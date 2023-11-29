import dayjs from 'dayjs';
import { useFormik } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import {
  Dialog,
  SingleAutocompleteField,
  TextField,
  useMutationQuery
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelCreateNewToken } from '../translatedLabels';

import { isCreateTokenAtom } from './atoms';
import { UnitDate } from './models';

const dataDuration = [
  { id: 'sevenDays', name: '7 days', unit: UnitDate.Day, value: 7 },
  { id: 'thirtyDays', name: '30 days', unit: UnitDate.Day, value: 30 },
  { id: 'sixtyDays', name: '60 days', unit: UnitDate.Day, value: 60 },
  { id: 'ninetyDays', name: '90 days', unit: UnitDate.Day, value: 90 },
  { id: 'oneYear', name: '1 year', unit: UnitDate.Year, value: 1 },
  { id: 'customize', name: 'Customize', unit: null, value: null }
];

const CreateTokenDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const [isCreateToken, setIsCreateToken] = useAtom(isCreateTokenAtom);
  const user = useAtomValue(userAtom);
  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => 'createTokenEndpoint',
    method: 'POST'
  });

  const formik = useFormik({
    initialValues: {
      duration: null,
      tokenName: '',
      userName: { id: user.id, name: user.name }
    },
    onSubmit: (values) => {
      alert(JSON.stringify(values, null, 2));
    },
    validationSchema: object({
      duration: object({
        id: string().required(),
        name: string().required()
      }).required('This field is required'),
      tokenName: string().required('This field is required')
    })
  });

  const getExpirationDate = ({ value, unit }): string => {
    return dayjs().add(value, unit).toISOString();
  };

  const confirm = (event): void => {
    formik.handleSubmit();
    mutateAsync({});
  };

  const closeDialog = (): void => {
    setIsCreateToken(false);
  };

  const changeDuration = (_, value): void => {
    formik.setFieldValue('duration', value);
  };

  const options = dataDuration.map(({ id, name }) => ({
    id,
    name
  }));

  console.log({ formik });

  return (
    <Dialog
      labelTitle={t(labelCreateNewToken)}
      open={isCreateToken}
      onCancel={closeDialog}
      onConfirm={confirm}
    >
      <TextField
        required
        dataTestId="tokenNameInput"
        error={formik.errors?.tokenName}
        helperText={formik.errors?.tokenName}
        id="tokenName"
        label="Token name"
        value={formik.values.tokenName}
        onChange={formik.handleChange}
      />
      <SingleAutocompleteField
        required
        error={formik.errors?.duration}
        id="duration"
        label="Duration"
        options={options}
        value={formik.values.duration}
        onChange={changeDuration}
      />
      <TextField
        disabled
        required
        dataTestId="userInput"
        id="userName"
        label="User"
        value={formik.values.userName.name}
      />
    </Dialog>
  );
};

export default CreateTokenDialog;
