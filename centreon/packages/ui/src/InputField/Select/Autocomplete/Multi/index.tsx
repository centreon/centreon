import { compose, includes, map, prop, reject, sortBy, toLower } from 'ramda';
import { forwardRef } from 'react';
import { makeStyles } from 'tss-react/mui';

import {
  Chip,
  ChipProps,
  ListSubheader,
  Tooltip,
  Typography
} from '@mui/material';
import { UseAutocompleteProps } from '@mui/material/useAutocomplete';

import { useTranslation } from 'react-i18next';
import Autocomplete, { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';
import { Button } from '../../../../components/Button';
import {
  labelElementsFound,
  labelSelectAll,
  labelUnSelectAll
} from '../../../translatedLabels';
import Option from '../../Option';

const useStyles = makeStyles()((theme) => ({
  deleteIcon: {
    height: theme.spacing(1.5),
    width: theme.spacing(1.5)
  },
  tag: {
    fontSize: theme.typography.caption.fontSize
  },
  lisSubHeader: {
    width: '100%',
    background: theme.palette.background.default,
    padding: theme.spacing(0.5, 1, 0.5, 1.5),
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center'
  },
  dropdown: {
    width: '100%',
    background: theme.palette.background.paper
  }
}));

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

export interface Props
  extends Omit<AutocompleteProps, 'renderTags' | 'renderOption' | 'multiple'>,
    Omit<
      UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
      'multiple'
    > {
  chipProps?: ChipProps;
  disableSortedOptions?: boolean;
  disableSelectAll?: boolean;
  getOptionTooltipLabel?: (option) => string;
  getTagLabel?: (option) => string;
  optionProperty?: string;
  customRenderTags?: (tags: React.ReactNode) => React.ReactNode;
  total?: number;
}

const CustomListbox = forwardRef(
  (
    { children, label, labelTotal, handleSelectAllToggle, total, ...props },
    ref
  ) => {
    const { classes } = useStyles();

    return (
      <ul ref={ref} {...props}>
        <ListSubheader sx={{ padding: 0 }}>
          <div className={classes.lisSubHeader}>
            <Typography variant="body2">{labelTotal}</Typography>
            <Button
              variant="ghost"
              size="small"
              onClick={handleSelectAllToggle}
            >
              {label}
            </Button>
          </div>
        </ListSubheader>
        <div className={classes.dropdown}>{children}</div>
      </ul>
    );
  }
);

const MultiAutocompleteField = ({
  value,
  options,
  disableSortedOptions = false,
  disableSelectAll = true,
  optionProperty = 'name',
  getOptionLabel = (option): string => option.name,
  getTagLabel = (option): string => option[optionProperty],
  getOptionTooltipLabel,
  chipProps,
  customRenderTags,
  onChange,
  total,
  ...props
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useStyles();

  const renderTags = (renderedValue, getTagProps): Array<JSX.Element> =>
    renderedValue.map((option, index) => {
      return (
        <Tooltip
          key={option.id}
          placement="top"
          title={getOptionTooltipLabel?.(option)}
        >
          <Chip
            classes={{
              deleteIcon: classes.deleteIcon,
              root: classes.tag
            }}
            data-testid={`tag-option-chip-${option.id}`}
            label={getTagLabel(option)}
            size="medium"
            {...getTagProps({ index })}
            {...chipProps}
            onDelete={(event) => chipProps?.onDelete?.(event, option)}
          />
        </Tooltip>
      );
    });

  const getLimitTagsText = (more): JSX.Element => <Option>{`+${more}`}</Option>;

  const values = (value as Array<SelectEntry>) || [];

  const isOptionSelected = ({ id }): boolean => {
    const valueIds = map(prop('id'), values);

    return includes(id, valueIds);
  };

  const sortByName = sortBy(compose(toLower, prop(optionProperty)));

  const autocompleteOptions = disableSortedOptions
    ? options
    : sortByName([...values, ...reject(isOptionSelected, options)]);

  const allSelected =
    options.length > 0 && options.every((opt) => isOptionSelected(opt));

  const handleSelectAllToggle = () => {
    const syntheticEvent = {} as React.SyntheticEvent;

    if (allSelected) {
      onChange?.(syntheticEvent, [], 'selectOption');

      return;
    }

    onChange?.(syntheticEvent, options, 'selectOption');
  };

  return (
    <Autocomplete
      disableCloseOnSelect
      displayOptionThumbnail
      multiple
      getLimitTagsText={getLimitTagsText}
      options={autocompleteOptions}
      renderOption={(renderProps, option, { selected }): JSX.Element => (
        <li
          key={option.id}
          {...(renderProps as React.HTMLAttributes<HTMLLIElement>)}
        >
          <Option checkboxSelected={selected}>{getOptionLabel(option)}</Option>
        </li>
      )}
      value={values}
      isOptionEqualToValue={(option, value) => option.id === value.id}
      renderTags={(renderedValue, getTagProps): React.ReactNode =>
        customRenderTags
          ? customRenderTags(renderTags(renderedValue, getTagProps))
          : renderTags(renderedValue, getTagProps)
      }
      ListboxComponent={
        disableSelectAll
          ? undefined
          : (listboxProps) => (
              <CustomListbox
                {...listboxProps}
                label={t(allSelected ? labelUnSelectAll : labelSelectAll)}
                handleSelectAllToggle={handleSelectAllToggle}
                labelTotal={t(labelElementsFound, {
                  total: total || options.length
                })}
              />
            )
      }
      getOptionLabel={getOptionLabel}
      onChange={onChange}
      {...props}
    />
  );
};

export default MultiAutocompleteField;
