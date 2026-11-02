import { ButtonBase } from '@mui/material';

import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { labelFilterBy } from '../../translatedLabels';

interface Props {
  children: ReactNode;
  className?: string;
  label: string;
  onFilter: () => void;
}

// A cell value that adds itself to the filters when clicked
const FilterableValue = ({
  children,
  className = '',
  label,
  onFilter
}: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <ButtonBase
      className={`min-w-0 justify-start rounded-sm text-left hover:underline focus-visible:underline ${className}`}
      onClick={(event) => {
        // The row click expands the changes
        event.stopPropagation();
        onFilter();
      }}
      title={t(labelFilterBy, { value: label })}
    >
      {children}
    </ButtonBase>
  );
};

export default FilterableValue;
