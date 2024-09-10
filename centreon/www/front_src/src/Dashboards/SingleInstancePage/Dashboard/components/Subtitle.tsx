import { ReactNode } from 'react';

import { equals, type } from 'ramda';
import { useTranslation } from 'react-i18next';

import HelpOutlineIcon from '@mui/icons-material/HelpOutline';
import { Typography } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

interface Props {
  children: ReactNode;
  secondaryLabel?: string | Array<string>;
}

const Subtitle = ({ children, secondaryLabel }: Props): JSX.Element => {
  const { t } = useTranslation();

  const containsSeveralSecondaryLabels = equals(type(secondaryLabel), 'Array');

  return (
    <Typography variant="subtitle1">
      <strong>{children}</strong>
      {secondaryLabel && (
        <Tooltip
          aria-label={
            containsSeveralSecondaryLabels
              ? secondaryLabel[0]
              : (secondaryLabel as string)
          }
          followCursor={false}
          label={
            containsSeveralSecondaryLabels ? (
              <>
                {secondaryLabel.map((label) => (
                  <p key={label}>{t(label)}</p>
                ))}
              </>
            ) : (
              t(secondaryLabel)
            )
          }
          placement="right"
        >
          <HelpOutlineIcon
            color="primary"
            sx={{ ml: 1, verticalAlign: 'middle' }}
          />
        </Tooltip>
      )}
    </Typography>
  );
};

export default Subtitle;
