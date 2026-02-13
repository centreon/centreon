import CopyIcon from '@mui/icons-material/FileCopyOutlined';

import { IconButton, TextField, useCopyToClipboard } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { tokenAtom } from '../../../atoms';
import {
  labelToken,
  labelTokenCopiedToTheClipboard,
  labelTokenCouldNotBeCopied
} from '../../../translatedLabels';
import { endAdornment } from './EndAdornment';
import { useStyles } from './TextField.styles';

const TokenField = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const token = useAtomValue(tokenAtom);
  const [isVisible, setIsVisible] = useState(false);

  const { copy } = useCopyToClipboard({
    errorMessage: t(labelTokenCouldNotBeCopied),
    successMessage: t(labelTokenCopiedToTheClipboard)
  });

  const handleVisibility = (): void => {
    setIsVisible(!isVisible);
  };

  const copyToken = (): void => {
    copy(token);
  };

  return (
    <div className={classes.container}>
      <TextField
        dataTestId="token"
        EndAdornment={endAdornment({ isVisible, onClick: handleVisibility })}
        fullWidth
        id="token"
        label={t(labelToken)}
        textFieldSlotsAndSlotProps={{
          slotProps: { htmlInput: { 'data-testid': 'tokenInput' } }
        }}
        type={isVisible ? 'text' : 'password'}
        value={token}
      />
      <IconButton ariaLabel="clipboard" onClick={copyToken}>
        <CopyIcon fontSize="small" />
      </IconButton>
    </div>
  );
};

export default TokenField;
