import { useFormikContext } from 'formik';
import * as Yup from 'yup';

import { Typography } from '@mui/material';
import HelpOutlineIcon from '@mui/icons-material/HelpOutline';

import { SelectEntry } from '../InputField/Select';
import { Listing } from '../api/models';

import {
  Group,
  InputProps,
  InputPropsWithoutGroup,
  InputType,
} from './Inputs/models';

export interface BasicForm {
  active: boolean;
  animals: Array<SelectEntry>;
  class: { id: number; name: string } | null;
  custom: string;
  email: string;
  group: { id: number; name: string } | null;
  isForced: boolean;
  language: string;
  name: string;
  password: string;
  scopes: Array<string>;
  sports: Array<SelectEntry>;
}

const selectEntryValidationSchema = Yup.object().shape({
  id: Yup.number().required('Required'),
  name: Yup.string().required('Required'),
});

export const basicFormValidationSchema = Yup.object().shape({
  active: Yup.boolean().required('Active is required'),
  animals: Yup.array().of(selectEntryValidationSchema.required('Required')),
  class: selectEntryValidationSchema.nullable().required('Required'),
  custom: Yup.string().required('Custom is required'),
  email: Yup.string().email('Invalid email').required('Email is required'),
  group: selectEntryValidationSchema.nullable().required('Required'),
  isForced: Yup.boolean().required('Is forced is required'),
  language: Yup.string().required('Language is required'),
  name: Yup.string().required('Name is required'),
  password: Yup.string().required('Password is required'),
  scopes: Yup.array().of(Yup.string().required('Required')),
  sports: Yup.array().of(selectEntryValidationSchema.required('Required')),
});

export const basicFormInitialValues = {
  active: false,
  animals: [],
  class: null,
  custom: '',
  email: '',
  group: null,
  isForced: false,
  language: 'French',
  name: '',
  password: '',
  scopes: [],
  sports: [],
};

export const classOptions = [...Array(10).keys()].map((idx) => ({
  id: idx,
  name: `Class ${idx}`,
}));

export const sportOptions = [...Array(10).keys()].map((idx) => ({
  id: idx,
  name: `Sport ${idx}`,
}));

export const basicFormGroups: Array<Group> = [
  {
    name: 'First group',
    order: 1,
  },
  {
    EndIcon: HelpOutlineIcon,
    TooltipContent: (): JSX.Element => <Typography>Tooltip content</Typography>,
    name: 'Second group',
    order: 2,
  },
];

export const basicFormInputs: Array<InputProps> = [
  {
    fieldName: 'name',
    group: 'First group',
    label: 'Name',
    type: InputType.Text,
  },
  {
    fieldName: 'email',
    group: 'First group',
    label: 'Email',
    type: InputType.Text,
  },
  {
    fieldName: 'active',
    group: 'Second group',
    label: 'Active',
    type: InputType.Switch,
  },
  {
    additionalLabel: 'This a very special label',
    fieldName: 'password',
    group: 'First group',
    label: 'Password',
    type: InputType.Password,
  },
  {
    fieldName: 'language',
    group: 'First group',
    label: 'Language',
    radio: {
      options: [
        {
          label: 'French',
          value: 'French',
        },
        {
          label: 'English',
          value: 'English',
        },
      ],
    },
    type: InputType.Radio,
  },
  {
    fieldName: 'isForced',
    group: 'First group',
    label: 'Is Forced?',
    radio: {
      options: [
        {
          label: 'Is not forced',
          value: false,
        },
        {
          label: 'Is forced',
          value: true,
        },
      ],
    },
    type: InputType.Radio,
  },
  {
    fieldName: '',
    grid: {
      columns: [
        {
          autocomplete: {
            options: classOptions,
          },
          fieldName: 'class',
          label: 'Class (Single autocomplete)',
          type: InputType.SingleAutocomplete,
        },
        {
          autocomplete: {
            options: sportOptions,
          },
          fieldName: 'sports',
          label: 'Sports (Multi autocomplete)',
          type: InputType.MultiAutocomplete,
        },
      ],
    },
    group: 'First group',
    label: 'autocompletes',
    type: InputType.Grid,
  },
  {
    autocomplete: {
      creatable: true,
      options: [],
    },
    fieldName: 'scopes',
    group: 'First group',
    label: 'Scopes (Multi autocomplete that allows value creation)',
    type: InputType.MultiAutocomplete,
  },
  {
    fieldName: '',
    grid: {
      columns: [
        {
          connectedAutocomplete: {
            endpoint: 'endpoint',
          },
          fieldName: 'group',
          label: 'Group (Single connected autocomplete)',
          type: InputType.SingleConnectedAutocomplete,
        },
        {
          connectedAutocomplete: {
            endpoint: 'endpoint',
          },
          fieldName: 'animals',
          label: 'Animals (Multi connected autocomplete)',
          type: InputType.MultiConnectedAutocomplete,
        },
      ],
      gridTemplateColumns: '400px 1fr',
    },
    group: 'First group',
    label: 'connected autocompletes',
    type: InputType.Grid,
  },
  {
    custom: {
      Component: ({ label }: InputPropsWithoutGroup): JSX.Element => (
        <Typography>This is a {label} component</Typography>
      ),
    },
    fieldName: 'custom',
    group: 'Second group',
    label: 'Custom',
    type: InputType.Custom,
  },
];

export const CustomButton = (): JSX.Element => {
  const { dirty, isValid } = useFormikContext();

  return (
    <div>
      <Typography>Has form changed? {JSON.stringify(dirty)}</Typography>
      <Typography>Is valid? {JSON.stringify(isValid)}</Typography>
    </div>
  );
};

const buildEntities = (from): Array<SelectEntry> => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      id: from + index,
      name: `Entity ${from + index}`,
    }));
};

export const buildResult = (page): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page,
    total: 40,
  },
  result: buildEntities((page - 1) * 10),
});
