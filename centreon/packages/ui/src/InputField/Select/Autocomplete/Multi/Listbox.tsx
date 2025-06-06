import { ListSubheader, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { Button } from '../../../../components/Button';
import { useListboxStyles } from './Multi.styles';

import {
  labelElementsFound,
  labelSelectAll,
  labelUnSelectAll
} from '../../../translatedLabels';

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
          <Button variant="ghost" size="small" onClick={handleSelectAllToggle}>
            {label}
          </Button>
        </div>
      </ListSubheader>
      <div className={classes.dropdown}>{children}</div>
    </ul>
  );
};

const ListboxComponent = ({
  disableSelectAll,
  options,
  isOptionSelected,
  onChange,
  total
}) => {
  if (disableSelectAll) {
    return;
  }

  return (listboxProps): JSX.Element | undefined => {
    const { t } = useTranslation();

    const allSelected =
      options.length > 0 && options.every((opt) => isOptionSelected(opt));

    const handleSelectAllToggle = (): void => {
      const syntheticEvent = {} as React.SyntheticEvent;

      if (allSelected) {
        onChange?.(syntheticEvent, [], 'selectOption');

        return;
      }

      onChange?.(syntheticEvent, options, 'selectOption');
    };

    return (
      <CustomListbox
        {...listboxProps}
        label={t(allSelected ? labelUnSelectAll : labelSelectAll)}
        handleSelectAllToggle={handleSelectAllToggle}
        labelTotal={t(labelElementsFound, {
          total: total || options.length
        })}
      />
    );
  };
};

export default ListboxComponent;
