import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { IconButton, TextField, useCopyToClipboard } from '@centreon/ui';
import CopyIcon from '@mui/icons-material/FileCopyOutlined';

import { endAdornment } from './EndAdornment';
import { useStyles } from './TextField.styles';

import { useAtomValue } from 'jotai';
import { tokenAtom } from '../../../atoms';
import {
  labelToken,
  labelTokenCopiedToTheClipboard,
  labelTokenCouldNotBeCopied
} from '../../../translatedLabels';

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
        fullWidth
        EndAdornment={endAdornment({ isVisible, onClick: handleVisibility })}
        dataTestId="token"
        id="token"
        inputProps={{ 'data-testid': 'tokenInput' }}
        label={t(labelToken)}
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
