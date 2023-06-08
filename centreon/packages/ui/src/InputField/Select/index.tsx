import clsx from 'clsx';
import { isNil, propEq } from 'ramda';

import {
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Theme,
  SelectProps,
  FormHelperText,
  ListSubheader,
  Divider,
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import Option from './Option';

const useStyles = makeStyles((theme: Theme) => ({
  compact: {
    fontSize: 'x-small',
    padding: theme.spacing(0.5),
  },
  input: {
    fontSize: theme.typography.body1.fontSize,
  },
  noLabelInput: {
    padding: theme.spacing(1),
  },
}));

export interface SelectEntry {
  color?: string;
  createOption?: string;
  disabled?: boolean;
  id: number | string;
  inputValue?: string;
  name: string;
  testId?: string;
  type?: 'header';
  url?: string;
}

type Props = {
  ariaLabel?: string;
  compact?: boolean;
  error?: string;
  label?: string;
  onChange;
  options: Array<SelectEntry>;
  selectedOptionId: number | string;
} & Omit<SelectProps, 'error'>;

const SelectField = ({
  options,
  onChange,
  selectedOptionId,
  label,
  error,
  fullWidth,
  ariaLabel,
  inputProps,
  compact = false,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  const getOption = (id): SelectEntry => {
    return options.find(propEq('id', id)) as SelectEntry;
  };

  const changeOption = (event): void => {
    if (!isNil(event.target.value)) {
      onChange(event);
    }
  };

  return (
    <FormControl
      error={!isNil(error)}
      fullWidth={fullWidth}
      size="small"
      variant="filled"
    >
      {label && <InputLabel>{label}</InputLabel>}
      <Select
        disableUnderline
        displayEmpty
        fullWidth={fullWidth}
        inputProps={{
          'aria-label': ariaLabel,
          className: clsx(classes.input, {
            [classes.noLabelInput]: !label && !compact,
            [classes.compact]: compact,
          }),
          ...inputProps,
        }}
        renderValue={(id): string => {
          return getOption(id)?.name;
        }}
        value={selectedOptionId}
        onChange={changeOption}
        {...props}
      >
        {options
          .filter(({ id }) => id !== '')
          .map(({ id, name, color, type, testId }) => {
            const key = `${id}-${name}`;
            if (type === 'header') {
              return [
                <ListSubheader key={key}>{name}</ListSubheader>,
                <Divider key={`${key}-divider`} />,
              ];
            }

            return (
              <MenuItem
                data-testid={testId}
                key={key}
                style={{ backgroundColor: color }}
                value={id}
              >
                <Option>{name}</Option>
              </MenuItem>
            );
          })}
      </Select>
      {error && <FormHelperText>{error}</FormHelperText>}
    </FormControl>
  );
};

export default SelectField;
