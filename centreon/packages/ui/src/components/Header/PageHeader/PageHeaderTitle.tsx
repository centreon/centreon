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
  const { classes, cx } = useStyles();

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

  const titleElement = (
    <MuiTypography
      aria-label="page header title"
      className={cx(isInline && 'shrink-0')}
      variant="h1"
    >
      {title}
    </MuiTypography>
  );

  const actionsElement = actions && (
    <div className={classes.pageHeaderTitleActions}>{actions}</div>
  );

  if (isInline) {
    return (
      <div className={cx(classes.pageHeaderTitle, 'min-w-0')}>
        {/* The row sits one level below, out of the rules that
        `pageHeaderTitle` applies to its direct children. */}
        <div className="min-w-0">
          <div className="flex min-w-0 items-baseline gap-4">
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
        </div>
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
