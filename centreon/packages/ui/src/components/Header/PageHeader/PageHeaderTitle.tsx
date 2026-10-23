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

  return (
    <div className={cx(classes.pageHeaderTitle, isInline && 'min-w-0')}>
      <span className={cx(isInline && 'items-baseline')}>
        <MuiTypography
          aria-label="page header title"
          className={cx(isInline && 'shrink-0')}
          variant="h1"
        >
          {title}
        </MuiTypography>
        {isInline && description && (
          <>
            <span aria-hidden className={classes.pageHeaderTitleSeparator}>
              |
            </span>
            <div className="min-w-0">{descriptionElement}</div>
          </>
        )}
        {actions && (
          <div className={classes.pageHeaderTitleActions}>{actions}</div>
        )}
      </span>
      {!isInline && descriptionElement}
    </div>
  );
};

export { PageHeaderTitle };
