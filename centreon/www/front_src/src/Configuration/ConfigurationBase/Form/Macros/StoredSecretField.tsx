import FormatClearIcon from '@mui/icons-material/FormatClear';
import RestartAltIcon from '@mui/icons-material/RestartAlt';

import { IconButton, TextField } from '@centreon/ui';

import { type ChangeEvent, type ReactElement, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelClear,
  labelRevertToPreviousPassword
} from '../../translatedLabels';

const mask = '********';

interface Props {
  autoComplete?: string;
  dataTestId: string;
  disabled?: boolean;
  error?: string;
  // Cleared by the user, who now types the value to replace it with.
  isErased: boolean;
  // A value the server keeps but never sends back.
  isStored: boolean;
  label: string;
  onBlur?: () => void;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onErase: () => void;
  onRevert: () => void;
  type?: string;
  value: string;
}

// A secret the server stores: masked and locked until the user clears it to
// type a new one, or reverts to keeping the stored one.
const StoredSecretField = ({
  autoComplete,
  dataTestId,
  disabled = false,
  error,
  isErased,
  isStored,
  label,
  onBlur,
  onChange,
  onErase,
  onRevert,
  type,
  value
}: Props): ReactElement => {
  const { t } = useTranslation();
  const inputRef = useRef<HTMLInputElement>(null);

  const isMasked = isStored && !isErased;

  // Cleared: ready to type in.
  useEffect(() => {
    if (isStored && isErased) {
      inputRef.current?.focus();
    }
  }, [isStored, isErased]);

  const action = isStored && !disabled && (
    <IconButton
      ariaLabel={t(isMasked ? labelClear : labelRevertToPreviousPassword)}
      dataTestid={`${dataTestId}-${isMasked ? 'clear' : 'revert'}`}
      onClick={isMasked ? onErase : onRevert}
      size="small"
      title={t(isMasked ? labelClear : labelRevertToPreviousPassword)}
    >
      {isMasked ? (
        <FormatClearIcon fontSize="small" />
      ) : (
        <RestartAltIcon fontSize="small" />
      )}
    </IconButton>
  );

  return (
    <TextField
      autoComplete={autoComplete}
      dataTestId={dataTestId}
      disabled={disabled || isMasked}
      EndAdornment={action || undefined}
      error={isMasked ? undefined : error}
      fullWidth
      inputRef={inputRef}
      label={label}
      onBlur={onBlur}
      onChange={onChange}
      type={isMasked ? 'password' : type}
      value={isMasked ? mask : value}
    />
  );
};

export default StoredSecretField;
