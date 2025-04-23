import { ReactElement, ReactNode } from 'react';

import { Typography as MuiTypography } from '@mui/material';

import { TextOverflowTooltip } from '../../Tooltip/TextOverflowTooltip';

import { useStyles } from './PageHeader.styles';

type PageHeaderTitleProps = {
  actions?: ReactNode;
  description?: string;
  title: string;
};

const PageHeaderTitle = ({
  actions,
  title,
  description
}: PageHeaderTitleProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.pageHeaderTitle}>
      <span>
        <MuiTypography aria-label="page header title" variant="h1">
          {title}
        </MuiTypography>
        {actions && (
          <div className={classes.pageHeaderTitleActions}>{actions}</div>
        )}
      </span>
      {description && (
        <TextOverflowTooltip label={description}>
          <MuiTypography
            aria-label="page header description"
            className={classes.pageHeaderTitleDescription}
            variant="body2"
          >
            {description}
          </MuiTypography>
        </TextOverflowTooltip>
      )}
    </div>
  );
};

export { PageHeaderTitle };
