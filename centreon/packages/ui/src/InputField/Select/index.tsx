import {
  Divider,
  FormControl,
  type FormControlProps,
  FormHelperText,
  InputLabel,
  ListSubheader,
  MenuItem,
  Select,
  type SelectChangeEvent,
  type SelectProps,
  type Theme
} from '@mui/material';

import { isNil, propEq } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { getNormalizedId } from '../../utils';
import Option from './Option';

const useStyles = makeStyles()((theme: Theme) => ({
  compact: {
    fontSize: 'x-small',
    padding: theme.spacing(0.75)
  },
  input: {
    fontSize: theme.typography.body1.fontSize
  },
  noLabelInput: {
    padding: theme.spacing(1)
  },
  select: {
    '& .MuiInputLabel-shrink~.MuiInputBase-root fieldset legend': {
      maxWidth: '100%'
    },
    '& fieldset legend': {
      maxWidth: 0
    }
  }
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
  dataTestId: string;
  error?: string;
  label?: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  options: Array<SelectEntry>;
  selectedOptionId: number | string;
  formControlProps?: FormControlProps;
} & Omit<SelectProps, 'error'>;

const SelectField = ({
  dataTestId,
  options,
  onChange,
  selectedOptionId,
  label,
  error,
  fullWidth,
  ariaLabel,
  inputProps,
  compact = false,
  formControlProps,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const getOption = (id: unknown): SelectEntry => {
    return options.find(propEq(id, 'id')) as SelectEntry;
  };

  const changeOption = (event: SelectChangeEvent<unknown>): void => {
    if (!isNil(event.target.value)) {
      onChange(event as unknown as React.ChangeEvent<HTMLInputElement>);
    }
  };

  return (
    <FormControl
      className={classes.select}
      error={!isNil(error)}
      fullWidth={fullWidth}
      size="small"
      {...formControlProps}
    >
      {label && <InputLabel>{label}</InputLabel>}
      <Select
        displayEmpty
        fullWidth={fullWidth}
        label={label}
        onChange={changeOption}
        renderValue={(id): string => {
          return getOption(id)?.name;
        }}
        slotProps={{
          input: {
            'aria-label': ariaLabel,
            className: cx(classes.input, {
              [classes.noLabelInput]: !label && !compact,
              [classes.compact]: compact
            }),
            id: getNormalizedId(dataTestId || ''),
            ...inputProps,
            ...({ 'data-testid': dataTestId } as Record<string, string>)
          }
        }}
        value={selectedOptionId}
        {...props}
      >
        {options
          .filter(({ id }) => id !== '')
          .map(({ id, name, color, type, testId }) => {
            const key = `${id}-${name}`;
            if (type === 'header') {
              return [
                <ListSubheader key={key}>{name}</ListSubheader>,
                <Divider key={`${key}-divider`} />
              ];
            }

            return (
              <MenuItem
                aria-label={name}
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
