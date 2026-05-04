import { ListSubheader, Typography } from '@mui/material';

import type React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '../../../../components/Button';
import {
  labelElementsFound,
  labelSelectAll,
  labelUnSelectAll
} from '../../../translatedLabels';
import type { SelectEntry } from '../..';
import { useListboxStyles } from './Multi.styles';

const CustomListbox = ({
  children,
  label,
  labelTotal,
  handleSelectAllToggle,
  ...props
}) => {
  const { classes } = useListboxStyles();

  return (
    <ul {...props}>
      <ListSubheader sx={{ padding: 0 }}>
        <div className={classes.lisSubHeader}>
          <Typography variant="body2">{labelTotal}</Typography>
          <Button onClick={handleSelectAllToggle} size="small" variant="ghost">
            {label}
          </Button>
        </div>
      </ListSubheader>
      <div className={classes.dropdown}>{children}</div>
    </ul>
  );
};

interface ListboxProps {
  disableSelectAll?: boolean;
  options: Array<SelectEntry>;
  isOptionSelected: (opt: SelectEntry) => boolean;
  onChange?: (
    event: React.SyntheticEvent,
    value: Array<SelectEntry>,
    reason: string
  ) => void;
  total?: number;
  value?: Array<SelectEntry>;
}

const ListboxComponent = ({
  disableSelectAll,
  options,
  isOptionSelected,
  onChange,
  total,
  value = []
}: ListboxProps) => {
  const { t } = useTranslation();

  if (disableSelectAll) {
    return;
  }

  return (listboxProps): JSX.Element | undefined => {
    const allSelected =
      options.length > 0 && options.every((opt) => isOptionSelected(opt));

    const handleSelectAllToggle = (): void => {
      const syntheticEvent = {} as React.SyntheticEvent;

      if (allSelected) {
        const remaining = value.filter(
          (v) => !options.some((opt) => opt.id === v.id)
        );
        onChange?.(syntheticEvent, remaining, 'selectOption');

        return;
      }

      const merged = [
        ...value,
        ...options.filter((opt) => !isOptionSelected(opt))
      ];
      onChange?.(syntheticEvent, merged, 'selectOption');
    };

    return (
      <CustomListbox
        {...listboxProps}
        handleSelectAllToggle={handleSelectAllToggle}
        label={t(allSelected ? labelUnSelectAll : labelSelectAll)}
        labelTotal={t(labelElementsFound, {
          total: total || options.length
        })}
      />
    );
  };
};

export default ListboxComponent;
