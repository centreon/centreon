import { Typography as MuiTypography } from '@mui/material';

import type { ReactElement, ReactNode } from 'react';

import { TextOverflowTooltip } from '../../Tooltip/TextOverflowTooltip';
import { useStyles } from './PageHeader.styles';

type PageHeaderTitleProps = {
  actions?: ReactNode;
  description?: string;
  descriptionPosition?: 'below' | 'inline';
  title: string;
};

const PageHeaderTitle = ({
  actions,
  title,
  description,
  descriptionPosition = 'below'
}: PageHeaderTitleProps): ReactElement => {
  const { classes } = useStyles();

  const isInline = descriptionPosition === 'inline';

  const descriptionElement = description && (
    <TextOverflowTooltip label={description}>
      <MuiTypography
        aria-label="page header description"
        className={classes.pageHeaderTitleDescription}
        variant="body2"
      >
        {description}
      </MuiTypography>
    </TextOverflowTooltip>
  );

  // Inline, the title owns its typography: the `pageHeaderTitle` rules that
  // style the `h1` of the stacked layout only apply to that layout.
  const titleElement = isInline ? (
    <MuiTypography
      aria-label="page header title"
      className="m-0 shrink-0 font-bold leading-none"
      component="h1"
      variant="h5"
    >
      {title}
    </MuiTypography>
  ) : (
    <MuiTypography aria-label="page header title" variant="h1">
      {title}
    </MuiTypography>
  );

  const actionsElement = actions && (
    <div className={classes.pageHeaderTitleActions}>{actions}</div>
  );

  if (isInline) {
    return (
      <div className="flex min-w-0 items-baseline gap-4 self-start">
        {titleElement}
        {description && (
          <>
            <span
              aria-hidden
              className="shrink-0 text-2xl font-light leading-none text-header-page-description"
            >
              |
            </span>
            <div className="min-w-0">{descriptionElement}</div>
          </>
        )}
        {actionsElement}
      </div>
    );
  }

  return (
    <div className={classes.pageHeaderTitle}>
      <span>
        {titleElement}
        {actionsElement}
      </span>
      {descriptionElement}
    </div>
  );
};

export { PageHeaderTitle };
