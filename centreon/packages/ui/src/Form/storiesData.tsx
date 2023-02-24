import { FormikValues, useFormikContext } from 'formik';
import * as Yup from 'yup';
import { equals, prop } from 'ramda';

import { Typography } from '@mui/material';
import HelpOutlineIcon from '@mui/icons-material/HelpOutline';
import MailIcon from '@mui/icons-material/MailOutline';
import SmsIcon from '@mui/icons-material/TextsmsOutlined';

import { SelectEntry } from '../InputField/Select';
import { Listing } from '../api/models';

import {
  Group,
  InputProps,
  InputPropsWithoutGroup,
  InputType
} from './Inputs/models';

export interface BasicForm {
  active: boolean;
  activeSortableFieldsTable: boolean;
  animals: Array<SelectEntry>;
  anotherText: string;
  class: { id: number; name: string } | null;
  custom: string;
  email: string;
  group: { id: number; name: string } | null;
  inviteUsers: Array<{
    role: SelectEntry;
    user: string;
  }>;
  inviteUsers2: Array<string>;
  isForced: boolean;
  language: string;
  name: string;
  password: string;
  roleMapping: Array<{
    role: SelectEntry;
    value: string;
  }>;
  scopes: Array<string>;
  sports: Array<SelectEntry>;
}

const selectEntryValidationSchema = Yup.object().shape({
  id: Yup.number().required('Required'),
  name: Yup.string().required('Required')
});

export const basicFormValidationSchema = Yup.object().shape({
  active: Yup.boolean().required('Active is required'),
  activeSortableFieldsTable: Yup.boolean().required(
    'Active Sortable FieldsTable is required'
  ),
  animals: Yup.array().of(selectEntryValidationSchema.required('Required')),
  anotherText: Yup.string(),
  class: selectEntryValidationSchema.nullable().required('Required'),
  custom: Yup.string().required('Custom is required'),
  email: Yup.string().email('Invalid email').required('Email is required'),
  group: selectEntryValidationSchema.nullable().required('Required'),
  inviteUsers: Yup.array().of(
    Yup.object({
      email: Yup.string()
        .email('Invalid user email')
        .required('Email is required'),
      role: selectEntryValidationSchema
    })
  ),
  inviteUsers2: Yup.array().of(Yup.string().email('Invalid user email')),
  isForced: Yup.boolean().required('Is forced is required'),
  language: Yup.string().required('Language is required'),
  name: Yup.string().required('Name is required'),
  password: Yup.string().required('Password is required'),
  roleMapping: Yup.array().of(
    Yup.object({
      role: selectEntryValidationSchema,
      value: Yup.string().required('Role value is required')
    })
  ),
  scopes: Yup.array().of(
    Yup.string().min(3, '3 characters min').required('Required')
  ),
  sports: Yup.array()
    .of(selectEntryValidationSchema.required('Required'))
    .min(2, 'Choose at least 2 sports')
});

const roleEntries: Array<SelectEntry> = [
  {
    id: 1,
    name: 'Administrator'
  },
  {
    id: 2,
    name: 'User'
  },
  {
    id: 3,
    name: 'Editor'
  }
];

export const basicFormInitialValues = {
  active: false,
  activeSortableFieldsTable: false,
  animals: [],
  class: { id: 0, name: 'Class 0' },
  custom: '',
  email: '',
  group: null,
  inviteUsers: [],
  inviteUsers2: [],
  isForced: false,
  language: 'French',
  name: '',
  notifications: {
    channels: [
      { Icon: MailIcon, checked: true, label: 'mail' },
      { Icon: SmsIcon, checked: false, label: 'Sms' },
      { Icon: MailIcon, checked: false, label: 'Slack' }
    ],
    hostevents: [
      { checked: false, label: 'Up' },
      { checked: true, label: 'Down' },
      { checked: false, label: 'Unreachable' },
      { checked: false, label: 'Warning' }
    ],
    includeServices: { checked: true, label: 'Include services for this host' }
  },
  password: '',
  roleMapping: [
    {
      priority: 0,
      role: roleEntries[0],
      value: 'example'
    },
    {
      priority: 1,
      role: roleEntries[1],
      value: 'example2'
    },
    {
      priority: 2,
      role: roleEntries[2],
      value: 'example3'
    }
  ],
  scopes: [],
  sports: []
};

export const classOptions = [...Array(10).keys()].map((idx) => ({
  id: idx,
  name: `Class ${idx}`
}));

export const sportOptions = [...Array(10).keys()].map((idx) => ({
  id: idx,
  name: `Sport ${idx}`
}));

export const basicFormGroups: Array<Group> = [
  {
    name: 'First group',
    order: 1
  },
  {
    EndIcon: () => <HelpOutlineIcon />,
    TooltipContent: (): JSX.Element => <Typography>Tooltip content</Typography>,
    name: 'Second group',
    order: 2
  },
  {
    name: 'Third group',
    order: 3
  }
];

export const basicFormInputs: Array<InputProps> = [
  {
    fieldName: 'name',
    group: 'First group',
    label: 'Name',
    type: InputType.Text
  },
  {
    fieldName: 'email',
    group: 'First group',
    label: 'Email',
    type: InputType.Text
  },
  {
    fieldName: 'active',
    group: 'Second group',
    label: 'Active',
    type: InputType.Switch
  },
  {
    additionalLabel: 'This a very special label',
    fieldName: 'password',
    group: 'First group',
    hideInput: (values) => values.active,
    label: 'Password',
    type: InputType.Password
  },
  {
    fieldName: 'language',
    group: 'First group',
    label: 'Language',
    radio: {
      options: [
        {
          label: 'French',
          value: 'French'
        },
        {
          label: 'English',
          value: 'English'
        }
      ]
    },
    type: InputType.Radio
  },
  {
    checkbox: {
      labelPlacement: 'top',
      row: true
    },
    fieldName: 'notifications.hostevents',
    group: 'Third group',
    label: 'host events',
    type: InputType.MultiCheckbox
  },
  {
    checkbox: {
      row: true
    },
    fieldName: 'notifications.channels',
    group: 'Third group',
    label: 'channels',
    type: InputType.MultiCheckbox
  },
  {
    fieldName: 'notifications.includeServices',
    group: 'Third group',
    label: 'Iclude services',
    type: InputType.Checkbox
  },
  {
    fieldName: 'anotherText',
    group: 'First group',
    hideInput: ({ language }) => equals(language, 'French'),
    label: 'Another Text input',
    type: InputType.Text
  },
  {
    fieldName: 'isForced',
    group: 'First group',
    label: 'Is Forced?',
    radio: {
      options: [
        {
          label: 'Is not forced',
          value: false
        },
        {
          label: 'Is forced',
          value: true
        }
      ]
    },
    type: InputType.Radio
  },
  {
    fieldName: '',
    grid: {
      columns: [
        {
          autocomplete: {
            options: classOptions
          },
          fieldName: 'class',
          label: 'Class (Single autocomplete)',
          type: InputType.SingleAutocomplete
        },
        {
          autocomplete: {
            options: sportOptions
          },
          fieldName: 'sports',
          label: 'Sports (Multi autocomplete)',
          type: InputType.MultiAutocomplete
        }
      ]
    },
    group: 'First group',
    label: 'autocompletes',
    type: InputType.Grid
  },
  {
    autocomplete: {
      creatable: true,
      options: []
    },
    fieldName: 'scopes',
    group: 'First group',
    label: 'Scopes (Multi autocomplete that allows value creation)',
    type: InputType.MultiAutocomplete
  },
  {
    fieldName: '',
    grid: {
      columns: [
        {
          connectedAutocomplete: {
            additionalConditionParameters: [],
            endpoint: 'endpoint'
          },
          fieldName: 'group',
          label: 'Group (Single connected autocomplete)',
          type: InputType.SingleConnectedAutocomplete
        },
        {
          connectedAutocomplete: {
            additionalConditionParameters: [],
            endpoint: 'endpoint'
          },
          fieldName: 'animals',
          label: 'Animals (Multi connected autocomplete)',
          type: InputType.MultiConnectedAutocomplete
        }
      ],
      gridTemplateColumns: '400px 1fr'
    },
    group: 'First group',
    label: 'connected autocompletes',
    type: InputType.Grid
  },
  {
    custom: {
      Component: ({ label }: InputPropsWithoutGroup): JSX.Element => (
        <Typography>This is a {label} component</Typography>
      )
    },
    fieldName: 'custom',
    group: 'Second group',
    label: 'Custom',
    type: InputType.Custom
  },
  {
    fieldName: 'inviteUsers',
    fieldsTable: {
      columns: [
        {
          fieldName: 'email',
          label: 'Email',
          required: true,
          type: InputType.Text
        },
        {
          autocomplete: {
            creatable: false,
            options: roleEntries
          },
          fieldName: 'role',
          label: 'Role',
          type: InputType.SingleAutocomplete
        }
      ],
      defaultRowValue: {
        email: 'example@test.fr',
        role: null
      },
      deleteLabel: 'Delete'
    },
    group: 'First group',
    label: 'inviteUsers',
    type: InputType.FieldsTable
  },
  {
    fieldName: 'inviteUsers2',
    fieldsTable: {
      columns: [
        {
          fieldName: 'email',
          label: 'Email',
          required: true,
          type: InputType.Text
        }
      ],
      defaultRowValue: 'example',
      deleteLabel: 'Delete',
      hasSingleValue: true
    },
    group: 'First group',
    label: 'inviteUsers2',
    type: InputType.FieldsTable
  },
  {
    fieldName: 'activeSortableFieldsTable',
    group: 'First group',
    label: 'Active Sortable Fields Table',
    type: InputType.Switch
  },
  {
    fieldName: 'roleMapping',
    fieldsTable: {
      columns: [
        {
          fieldName: 'value',
          label: 'RoleValue',
          required: true,
          type: InputType.Text
        },
        {
          autocomplete: {
            creatable: false,
            options: roleEntries
          },
          fieldName: 'role',
          label: 'RoleAcl',
          type: InputType.SingleAutocomplete
        }
      ],
      defaultRowValue: {
        role: null,
        value: ''
      },
      deleteLabel: 'Delete',
      getSortable: (values: FormikValues): boolean =>
        prop('activeSortableFieldsTable', values)
    },
    group: 'First group',
    label: 'roleMapping',
    type: InputType.FieldsTable
  }
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
      name: `Entity ${from + index}`
    }));
};

export const buildResult = (page): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page,
    total: 40
  },
  result: buildEntities((page - 1) * 10)
});
